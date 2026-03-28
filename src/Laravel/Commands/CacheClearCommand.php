<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Laravel\Commands;

use Illuminate\Console\Command;
use Micilini\VideoStream\Contracts\CacheHandlerInterface;

class CacheClearCommand extends Command
{
    protected $signature = 'video-stream:cache-clear
                            {--expired : Only remove expired files (keep valid cache)}';

    protected $description = 'Clear the VideoStream disk cache';

    public function handle(): int
    {
        $cache = app(CacheHandlerInterface::class);

        if ($cache === null) {
            $this->error('VideoStream cache is not enabled. Set VIDEO_STREAM_CACHE=true in your .env file.');
            return self::FAILURE;
        }

        if ($this->option('expired')) {
            $deleted = $cache->clearExpired();
            $this->info("Cleared {$deleted} expired file(s) from the video cache.");
        } else {
            $deleted = $cache->flush();
            $this->info("Cleared {$deleted} file(s) from the video cache.");
        }

        return self::SUCCESS;
    }
}
