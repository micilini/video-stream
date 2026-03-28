<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Cache\FileCacheHandler;
use Micilini\VideoStream\Exceptions\CacheException;
use Micilini\VideoStream\Tests\TestCase;

final class FileCacheHandlerTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachePath = $this->tmpPath() . DIRECTORY_SEPARATOR . 'cache-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cachePath)) {
            $files = glob($this->cachePath . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($this->cachePath);
        }
        parent::tearDown();
    }

    public function test_constructor_creates_cache_directory(): void
    {
        $this->assertDirectoryDoesNotExist($this->cachePath);

        new FileCacheHandler(cachePath: $this->cachePath);

        $this->assertDirectoryExists($this->cachePath);
    }

    public function test_constructor_creates_gitignore(): void
    {
        new FileCacheHandler(cachePath: $this->cachePath);

        $gitignore = $this->cachePath . DIRECTORY_SEPARATOR . '.gitignore';
        $this->assertFileExists($gitignore);
        $this->assertStringContainsString('!.gitignore', file_get_contents($gitignore));
    }

    public function test_has_returns_false_when_empty(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        $this->assertFalse($cache->has('https://example.com/video.mp4'));
    }

    public function test_disk_usage_is_zero_when_empty(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        $this->assertSame(0, $cache->diskUsage());
    }

    public function test_flush_on_empty_cache_returns_zero(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        $this->assertSame(0, $cache->flush());
    }

    public function test_clear_expired_on_empty_cache_returns_zero(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        $this->assertSame(0, $cache->clearExpired());
    }

    public function test_forget_returns_false_for_uncached_url(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        $this->assertFalse($cache->forget('https://example.com/nonexistent.mp4'));
    }

    public function test_has_with_manual_file(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath, defaultTtl: 3600);

        $url = 'https://example.com/test-video.mp4';
        $hash = hash('sha256', $url);
        $filePath = $this->cachePath . DIRECTORY_SEPARATOR . $hash . '.mp4';
        file_put_contents($filePath, 'fake video data');

        $this->assertTrue($cache->has($url));
    }

    public function test_has_returns_false_for_expired_file(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath, defaultTtl: 1);

        $url = 'https://example.com/expired-video.mp4';
        $hash = hash('sha256', $url);
        $filePath = $this->cachePath . DIRECTORY_SEPARATOR . $hash . '.mp4';
        file_put_contents($filePath, 'fake video data');

        touch($filePath, time() - 10);

        $this->assertFalse($cache->has($url));
    }

    public function test_forget_removes_cached_file(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        $url = 'https://example.com/to-forget.mp4';
        $hash = hash('sha256', $url);
        $filePath = $this->cachePath . DIRECTORY_SEPARATOR . $hash . '.mp4';
        file_put_contents($filePath, 'fake video data');

        $this->assertFileExists($filePath);
        $this->assertTrue($cache->forget($url));
        $this->assertFileDoesNotExist($filePath);
    }

    public function test_disk_usage_calculates_correctly(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        $file1 = $this->cachePath . DIRECTORY_SEPARATOR . 'test1.mp4';
        $file2 = $this->cachePath . DIRECTORY_SEPARATOR . 'test2.mp4';
        file_put_contents($file1, str_repeat('A', 1000));
        file_put_contents($file2, str_repeat('B', 2000));

        $this->assertSame(3000, $cache->diskUsage());
    }

    public function test_flush_removes_all_files(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        file_put_contents($this->cachePath . DIRECTORY_SEPARATOR . 'a.mp4', 'data');
        file_put_contents($this->cachePath . DIRECTORY_SEPARATOR . 'b.mp4', 'data');
        file_put_contents($this->cachePath . DIRECTORY_SEPARATOR . 'c.mp4', 'data');

        $deleted = $cache->flush();

        $this->assertSame(3, $deleted);
        $this->assertSame(0, $cache->diskUsage());
    }

    public function test_flush_preserves_gitignore(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        file_put_contents($this->cachePath . DIRECTORY_SEPARATOR . 'video.mp4', 'data');
        $cache->flush();

        $this->assertFileExists($this->cachePath . DIRECTORY_SEPARATOR . '.gitignore');
    }

    public function test_clear_expired_only_removes_old_files(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath, defaultTtl: 60);

        $fresh = $this->cachePath . DIRECTORY_SEPARATOR . 'fresh.mp4';
        file_put_contents($fresh, 'fresh data');

        $expired = $this->cachePath . DIRECTORY_SEPARATOR . 'expired.mp4';
        file_put_contents($expired, 'expired data');
        touch($expired, time() - 120);

        $deleted = $cache->clearExpired();

        $this->assertSame(1, $deleted);
        $this->assertFileExists($fresh);
        $this->assertFileDoesNotExist($expired);
    }

    public function test_tmp_files_are_ignored(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        file_put_contents($this->cachePath . DIRECTORY_SEPARATOR . 'download.mp4.tmp', 'incomplete');
        file_put_contents($this->cachePath . DIRECTORY_SEPARATOR . 'complete.mp4', 'done');

        $this->assertSame((int) filesize($this->cachePath . DIRECTORY_SEPARATOR . 'complete.mp4'), $cache->diskUsage());
    }

    public function test_resolve_with_invalid_url_throws(): void
    {
        $cache = new FileCacheHandler(cachePath: $this->cachePath);

        $this->expectException(CacheException::class);

        $cache->resolve('http://192.0.2.1/nonexistent.mp4', ttl: 3600);
    }
}
