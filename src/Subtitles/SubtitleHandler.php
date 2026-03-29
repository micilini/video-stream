<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Subtitles;

use Micilini\VideoStream\Exceptions\VideoNotFoundException;

final class SubtitleHandler
{
    public static function output(string $path): void
    {
        if (!is_file($path)) {
            throw new VideoNotFoundException('Subtitle file not found: ' . $path);
        }

        header('Content-Type: text/vtt; charset=utf-8');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: public, max-age=3600');

        readfile($path);
    }
}