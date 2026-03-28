<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Laravel\Commands;

use Illuminate\Console\Command;
use Micilini\VideoStream\Contracts\CacheHandlerInterface;

class CacheStatusCommand extends Command
{
    protected $signature = 'video-stream:cache-status';

    protected $description = 'Display VideoStream disk cache usage and statistics';

    public function handle(): int
    {
        $cache = app(CacheHandlerInterface::class);

        if ($cache === null) {
            $this->error('VideoStream cache is not enabled. Set VIDEO_STREAM_CACHE=true in your .env file.');
            return self::FAILURE;
        }

        $config = config('video-stream.cache');

        $diskUsage = $cache->diskUsage();
        $maxDisk = $config['max_disk_usage'];
        $ttl = $config['ttl'];
        $path = $config['path'];

        $usagePercent = $maxDisk > 0 ? round(($diskUsage / $maxDisk) * 100, 1) : 0;

        $this->info('VideoStream Cache Status');
        $this->line('');

        $this->table(
            ['Setting', 'Value'],
            [
                ['Cache Path', $path],
                ['TTL', $this->formatDuration($ttl)],
                ['Disk Usage', $this->formatBytes($diskUsage) . " / " . $this->formatBytes($maxDisk) . " ({$usagePercent}%)"],
                ['Status', $diskUsage > 0 ? 'Active' : 'Empty'],
            ]
        );

        $files = glob($path . DIRECTORY_SEPARATOR . '*');
        $fileCount = 0;
        if ($files !== false) {
            $fileCount = count(array_filter($files, function ($f) {
                $b = basename($f);
                return $b !== '.gitignore' && !str_ends_with($b, '.tmp') && is_file($f);
            }));
        }

        $this->line("Cached videos: {$fileCount}");

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return round($bytes / 1_073_741_824, 2) . ' GB';
        }
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds >= 86400) {
            $days = floor($seconds / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '');
        }
        if ($seconds >= 3600) {
            $hours = floor($seconds / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        if ($seconds >= 60) {
            $minutes = floor($seconds / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }
        return $seconds . ' second' . ($seconds > 1 ? 's' : '');
    }
}
