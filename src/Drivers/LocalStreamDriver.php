<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Drivers;

use Micilini\VideoStream\Contracts\StreamDriverInterface;
use Micilini\VideoStream\Exceptions\VideoNotFoundException;

final class LocalStreamDriver implements StreamDriverInterface
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(
        private readonly string $path,
    ) {
        if (!file_exists($this->path)) {
            throw new VideoNotFoundException("Video not found: {$this->path}");
        }
    }

    public function getSize(): ?int
    {
        $size = @filesize($this->path);
        return $size !== false ? $size : null;
    }

    public function stream(int $start, int $end, int $bufferSize): void
    {
        $this->handle = @fopen($this->path, 'rb');
        if ($this->handle === false) {
            throw new VideoNotFoundException("Could not open file for reading: {$this->path}");
        }

        set_time_limit(0);

        if ($start > 0) {
            fseek($this->handle, $start);
        }

        $bytesRemaining = $end - $start + 1;

        while (!feof($this->handle) && $bytesRemaining > 0) {
            $readSize = min($bufferSize, $bytesRemaining);
            $data = fread($this->handle, $readSize);

            if ($data === false) {
                break;
            }

            echo $data;
            flush();
            $bytesRemaining -= strlen($data);
        }
    }

    public function close(): void
    {
        if ($this->handle !== null && is_resource($this->handle)) {
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
