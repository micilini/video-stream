<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Laravel;

use Illuminate\Support\ServiceProvider;
use Micilini\VideoStream\VideoStream;
use Micilini\VideoStream\Cache\FileCacheHandler;
use Micilini\VideoStream\Contracts\CacheHandlerInterface;
use Micilini\VideoStream\Laravel\LaravelEventDispatcher;

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
            $eventsConfig = $app['config']['video-stream.events'] ?? ['enabled' => false];

            if (!$config['enabled']) {
                return null;
            }

            $eventDispatcher = null;

            if (($eventsConfig['enabled'] ?? false) === true) {
                $eventDispatcher = new LaravelEventDispatcher($app['events']);
            }

            return new FileCacheHandler(
                cachePath: $config['path'],
                defaultTtl: $config['ttl'],
                maxDiskUsage: $config['max_disk_usage'],
                eventDispatcher: $eventDispatcher,
            );
        });

        $this->app->singleton(VideoStream::class, function ($app) {
            $config = $app['config']['video-stream'];
            $eventDispatcher = null;

            if (($config['events']['enabled'] ?? false) === true) {
                $eventDispatcher = new LaravelEventDispatcher($app['events']);
            }

            $stream = new VideoStream(
                defaultBufferSize: $config['buffer_size'],
                defaultContentType: $config['default_content_type'],
                cacheHandler: $app->make(CacheHandlerInterface::class),
                eventDispatcher: $eventDispatcher,
            );

            if (($config['rate_limit']['enabled'] ?? false) === true) {
                $stream->rateLimit(
                    maxConcurrent: (int) ($config['rate_limit']['max_concurrent'] ?? 3),
                    maxPerMinute: (int) ($config['rate_limit']['max_per_minute'] ?? 30),
                    storagePath: (string) ($config['rate_limit']['storage_path'] ?? ''),
                );
            }

            return $stream;
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
