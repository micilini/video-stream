<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Http;

use Micilini\VideoStream\Exceptions\RateLimitExceededException;

final class RateLimiter
{
    private ?string $activeLockPath = null;

    public function __construct(
        private readonly string $storagePath,
        private readonly int $maxConcurrent,
        private readonly int $maxPerMinute,
        private readonly ?string $ipAddress = null,
    ) {
        if ($this->storagePath === '') {
            throw new \InvalidArgumentException('Rate limiter storage path cannot be empty.');
        }

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
    }

    public function acquire(): void
    {
        $ipHash = $this->getIpHash();

        if ($this->maxPerMinute > 0) {
            $this->assertPerMinuteLimit($ipHash);
        }

        if ($this->maxConcurrent > 0) {
            $this->assertConcurrentLimit($ipHash);
            $this->createLockFile($ipHash);
        }
    }

    public function release(): void
    {
        if ($this->activeLockPath !== null && is_file($this->activeLockPath)) {
            @unlink($this->activeLockPath);
        }

        $this->activeLockPath = null;
    }

    private function assertPerMinuteLimit(string $ipHash): void
    {
        $counterFile = $this->storagePath . DIRECTORY_SEPARATOR . $ipHash . '.count';
        $now = time();
        $windowStart = $now - 60;

        $entries = [];

        if (is_file($counterFile)) {
            $decoded = json_decode((string) file_get_contents($counterFile), true);

            if (is_array($decoded)) {
                $entries = array_values(array_filter(
                    $decoded,
                    static fn ($timestamp): bool => is_int($timestamp) && $timestamp >= $windowStart
                ));
            }
        }

        if (count($entries) >= $this->maxPerMinute) {
            throw new RateLimitExceededException(
                sprintf('Rate limit exceeded: max %d requests per minute for this IP.', $this->maxPerMinute)
            );
        }

        $entries[] = $now;

        file_put_contents(
            $counterFile,
            json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function assertConcurrentLimit(string $ipHash): void
    {
        $pattern = $this->storagePath . DIRECTORY_SEPARATOR . $ipHash . '.*.lock';
        $locks = glob($pattern) ?: [];
        $activeLocks = [];

        foreach ($locks as $lockFile) {
            if (!is_file($lockFile)) {
                continue;
            }

            $mtime = @filemtime($lockFile);

            if ($mtime !== false && $mtime < (time() - 3600)) {
                @unlink($lockFile);
                continue;
            }

            $activeLocks[] = $lockFile;
        }

        if (count($activeLocks) >= $this->maxConcurrent) {
            throw new RateLimitExceededException(
                sprintf('Rate limit exceeded: max %d concurrent streams for this IP.', $this->maxConcurrent)
            );
        }
    }

    private function createLockFile(string $ipHash): void
    {
        $lockPath = $this->storagePath . DIRECTORY_SEPARATOR . $ipHash . '.' . uniqid('', true) . '.lock';

        file_put_contents($lockPath, (string) time());
        $this->activeLockPath = $lockPath;
    }

    private function getIpHash(): string
    {
        $ip = $this->ipAddress;

        if ($ip === null || $ip === '') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        return hash('sha256', $ip);
    }
}