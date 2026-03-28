<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Drivers;

use Micilini\VideoStream\Contracts\StreamDriverInterface;
use Micilini\VideoStream\Exceptions\StreamException;

final class RemoteStreamDriver implements StreamDriverInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    public function __construct(
        private readonly string $url,
    ) {
        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw new StreamException("Invalid URL: {$this->url}");
        }
    }

    public function getSize(): ?int
    {
        $headers = @get_headers($this->url, true);

        if ($headers === false) {
            return null;
        }

        $headers = array_change_key_case($headers, CASE_LOWER);

        if (!isset($headers['content-length'])) {
            return null;
        }

        $cl = $headers['content-length'];

        if (is_array($cl)) {
            $cl = end($cl);
        }

        return (is_numeric($cl) && $cl > 0) ? (int) $cl : null;
    }

    public function stream(int $start, int $end, int $bufferSize): void
    {
        set_time_limit(0);

        $ch = curl_init($this->url);

        if ($ch === false) {
            throw new StreamException("Failed to initialize cURL for: {$this->url}");
        }

        curl_setopt_array($ch, [
            CURLOPT_RANGE          => "{$start}-{$end}",
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_BUFFERSIZE     => $bufferSize,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) {
                echo $data;
                flush();
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    public function close(): void
    {
        
    }
}
