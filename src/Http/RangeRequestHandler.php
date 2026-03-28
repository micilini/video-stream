<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Http;

use Micilini\VideoStream\Config\StreamConfig;

final class RangeRequestHandler
{
    private int $start = 0;
    private int $end;
    private int $statusCode = 200;

    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        private readonly int $totalSize,
        private readonly StreamConfig $config,
    ) {
        $this->end = $this->totalSize - 1;
        $this->buildHeaders();
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function sendHeaders(): void
    {
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->statusCode === 206) {
            header('HTTP/1.1 206 Partial Content', true, 206);
        }
    }

    private function buildHeaders(): void
    {
        $this->headers = [
            'Content-Type' => $this->config->contentType,
            'Cache-Control' => $this->config->cacheControl,
            'Expires' => $this->config->expires,
            'Last-Modified' => $this->config->lastModified,
            'Accept-Ranges' => 'bytes',
        ];

        $rangeHeader = $_SERVER['HTTP_RANGE'] ?? null;

        if ($rangeHeader === null) {
            $this->headers['Content-Length'] = (string) $this->totalSize;
            return;
        }

        [, $range] = explode('=', $rangeHeader, 2);

        if (str_contains($range, ',')) {
            $this->sendRangeNotSatisfiable();
            return;
        }

        if ($range === '-') {
            $this->start = $this->totalSize - (int) substr($range, 1);
        } else {
            $parts = explode('-', $range);
            $this->start = (int) $parts[0];
            if (isset($parts[1]) && is_numeric($parts[1])) {
                $this->end = (int) $parts[1];
            }
        }

        if ($this->end > $this->totalSize - 1) {
            $this->end = $this->totalSize - 1;
        }

        if ($this->start > $this->end || $this->start > $this->totalSize - 1 || $this->end >= $this->totalSize) {
            $this->sendRangeNotSatisfiable();
            return;
        }

        $length = $this->end - $this->start + 1;

        $this->statusCode = 206;
        $this->headers['Content-Length'] = (string) $length;
        $this->headers['Content-Range'] = "bytes {$this->start}-{$this->end}/{$this->totalSize}";
    }

    private function sendRangeNotSatisfiable(): void
    {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes 0-{$this->end}/{$this->totalSize}");
        exit;
    }
}
