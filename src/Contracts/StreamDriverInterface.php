<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Contracts;

interface StreamDriverInterface
{
    public function getSize(): ?int;

    public function stream(int $start, int $end, int $bufferSize): void;

    public function close(): void;
}
