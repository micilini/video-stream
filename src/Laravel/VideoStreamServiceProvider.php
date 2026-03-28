<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Laravel;

use Illuminate\Support\ServiceProvider;
use Micilini\VideoStream\VideoStream;
use Micilini\VideoStream\Cache\FileCacheHandler;
use Micilini\VideoStream\Contracts\CacheHandlerInterface;

class VideoStreamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/video-stream.php',
            'video-stream'
        );

        $this->app->singleton(CacheHandlerInterface::class, function ($app) {
            $config = $app['config']['video-stream.cache'];

            if (!$config['enabled']) {
                return null;
            }

            return new FileCacheHandler(
                cachePath: $config['path'],
                defaultTtl: $config['ttl'],
                maxDiskUsage: $config['max_disk_usage'],
            );
        });

        $this->app->singleton(VideoStream::class, function ($app) {
            $config = $app['config']['video-stream'];

            return new VideoStream(
                defaultBufferSize: $config['buffer_size'],
                defaultContentType: $config['default_content_type'],
                cacheHandler: $app->make(CacheHandlerInterface::class),
            );
        });

        $this->app->alias(VideoStream::class, 'video-stream');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/video-stream.php' => config_path('video-stream.php'),
            ], 'video-stream-config');

            $this->commands([
                Commands\CacheClearCommand::class,
                Commands\CacheStatusCommand::class,
            ]);
        }
    }
}
