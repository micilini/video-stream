<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Cache;

use Micilini\VideoStream\Contracts\CacheHandlerInterface;
use Micilini\VideoStream\Exceptions\CacheException;

final class FileCacheHandler implements CacheHandlerInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    public function __construct(
        private readonly string $cachePath,
        private readonly int $defaultTtl = 86400,
        private readonly int $maxDiskUsage = 10_737_418_240,
    ) {
        $this->ensureCacheDirectory();
    }

    public function resolve(string $url, ?int $ttl = null, bool $fresh = false): string
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $filePath = $this->keyToPath($url);

        if ($fresh) {
            $this->downloadToCache($url, $filePath);
            return $filePath;
        }

        if (file_exists($filePath)) {
            if (!$this->isExpired($filePath, $ttl)) {
                @touch($filePath);
                return $filePath;
            }

            try {
                $this->downloadToCache($url, $filePath);
                return $filePath;
            } catch (CacheException) {
                @touch($filePath);
                return $filePath;
            }
        }

        $this->downloadToCache($url, $filePath);
        return $filePath;
    }

    public function has(string $url, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $filePath = $this->keyToPath($url);

        return file_exists($filePath) && !$this->isExpired($filePath, $ttl);
    }

    public function forget(string $url): bool
    {
        $filePath = $this->keyToPath($url);

        if (file_exists($filePath)) {
            return @unlink($filePath);
        }

        return false;
    }

    public function clearExpired(?int $ttl = null): int
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $deleted = 0;

        $files = $this->getCacheFiles();

        foreach ($files as $file) {
            if ($this->isExpired($file, $ttl)) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public function flush(): int
    {
        $deleted = 0;

        $files = $this->getCacheFiles();

        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function diskUsage(): int
    {
        $total = 0;

        $files = $this->getCacheFiles();

        foreach ($files as $file) {
            $total += (int) @filesize($file);
        }

        return $total;
    }

    private function downloadToCache(string $url, string $destination): void
    {
        $this->enforceMaxDiskUsage();

        $tempPath = $destination . '.tmp';
        $fp = @fopen($tempPath, 'wb');

        if ($fp === false) {
            throw new CacheException("Cannot write to cache directory: {$this->cachePath}");
        }

        $ch = curl_init($url);

        if ($ch === false) {
            @fclose($fp);
            @unlink($tempPath);
            throw new CacheException("Failed to initialize cURL for: {$url}");
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $success = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($success && $httpCode >= 200 && $httpCode < 300) {
            if (!@rename($tempPath, $destination)) {
                @unlink($tempPath);
                throw new CacheException("Failed to move cached file to: {$destination}");
            }
        } else {
            @unlink($tempPath);
            throw new CacheException(
                "Failed to download: {$url} (HTTP {$httpCode}" . ($curlError ? ", {$curlError}" : '') . ")"
            );
        }
    }

    private function keyToPath(string $url): string
    {
        $hash = hash('sha256', $url);

        $urlPath = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = pathinfo($urlPath, PATHINFO_EXTENSION);
        $ext = preg_match('/^[a-zA-Z0-9]{1,10}$/', $ext) ? $ext : 'mp4';

        return $this->cachePath . DIRECTORY_SEPARATOR . $hash . '.' . $ext;
    }

    private function isExpired(string $filePath, int $ttl): bool
    {
        $mtime = @filemtime($filePath);

        if ($mtime === false) {
            return true;
        }

        return (time() - $mtime) > $ttl;
    }

    private function enforceMaxDiskUsage(): void
    {
        $currentUsage = $this->diskUsage();

        if ($currentUsage <= $this->maxDiskUsage) {
            return;
        }

        $files = $this->getCacheFiles();

        usort($files, function (string $a, string $b): int {
            return (int) @filemtime($a) <=> (int) @filemtime($b);
        });

        foreach ($files as $file) {
            if ($currentUsage <= $this->maxDiskUsage) {
                break;
            }

            $fileSize = (int) @filesize($file);

            if (@unlink($file)) {
                $currentUsage -= $fileSize;
            }
        }
    }

    private function getCacheFiles(): array
    {
        $pattern = $this->cachePath . DIRECTORY_SEPARATOR . '*';
        $files = glob($pattern);

        if ($files === false) {
            return [];
        }

        return array_filter($files, function (string $file): bool {
            $basename = basename($file);
            return $basename !== '.gitignore'
                && !str_ends_with($basename, '.tmp')
                && is_file($file);
        });
    }

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cachePath)) {
            if (!@mkdir($this->cachePath, 0775, true)) {
                throw new CacheException("Cannot create cache directory: {$this->cachePath}");
            }
        }

        $gitignore = $this->cachePath . DIRECTORY_SEPARATOR . '.gitignore';
        if (!file_exists($gitignore)) {
            @file_put_contents($gitignore, "*\n!.gitignore\n");
        }
    }
}