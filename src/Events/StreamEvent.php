<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Events;

abstract class StreamEvent
{
    public function __construct(
        public readonly string $name,
        public readonly array $context = [],
    ) {
    }

    public function all(): array
    {
        return $this->context;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }
}