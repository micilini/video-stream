<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Cache\FileCacheHandler;
use Micilini\VideoStream\Events\CallbackEventDispatcher;
use Micilini\VideoStream\Tests\TestCase;

final class FileCacheHandlerEventsTest extends TestCase
{
    public function test_dispatches_cache_hit_event_when_cached_file_is_valid(): void
    {
        $url = 'https://example.com/video.mp4';
        $events = [];
        $dispatcher = new CallbackEventDispatcher();

        $dispatcher->on('onCacheHit', function (array $context) use (&$events): void {
            $events[] = $context;
        });

        $handler = new FileCacheHandler(
            cachePath: $this->tmpPath(),
            defaultTtl: 3600,
            maxDiskUsage: 1024 * 1024,
            eventDispatcher: $dispatcher,
        );

        $hash = hash('sha256', $url);
        $cachedFile = $this->tmpPath($hash . '.mp4');
        file_put_contents($cachedFile, 'cached-video');

        $resolved = $handler->resolve($url, 3600, false);

        $this->assertSame($cachedFile, $resolved);
        $this->assertCount(1, $events);
        $this->assertSame($url, $events[0]['url']);
        $this->assertSame($cachedFile, $events[0]['localPath']);
        $this->assertFalse($events[0]['staleFallback']);
    }
}