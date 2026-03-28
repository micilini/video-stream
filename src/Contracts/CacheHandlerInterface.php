<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Contracts;

interface CacheHandlerInterface
{
    public function resolve(string $url, ?int $ttl = null, bool $fresh = false): string;

    public function has(string $url, ?int $ttl = null): bool;

    public function forget(string $url): bool;

    public function clearExpired(?int $ttl = null): int;

    public function flush(): int;

    public function diskUsage(): int;
}
