<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Exceptions\RateLimitExceededException;
use Micilini\VideoStream\Http\RateLimiter;
use Micilini\VideoStream\Tests\TestCase;

final class RateLimiterTest extends TestCase
{
    public function test_allows_request_within_per_minute_limit(): void
    {
        $limiter = new RateLimiter(
            storagePath: $this->tmpPath(),
            maxConcurrent: 0,
            maxPerMinute: 2,
            ipAddress: '127.0.0.1',
        );

        $limiter->acquire();

        $this->assertTrue(true);
    }

    public function test_blocks_when_per_minute_limit_is_exceeded(): void
    {
        $storage = $this->tmpPath('rate-limit-per-minute-' . uniqid('', true));

        $limiterA = new RateLimiter(
            storagePath: $storage,
            maxConcurrent: 0,
            maxPerMinute: 1,
            ipAddress: '127.0.0.1',
        );

        $limiterA->acquire();

        $limiterB = new RateLimiter(
            storagePath: $storage,
            maxConcurrent: 0,
            maxPerMinute: 1,
            ipAddress: '127.0.0.1',
        );

        $this->expectException(RateLimitExceededException::class);

        $limiterB->acquire();
    }

    public function test_blocks_when_concurrent_limit_is_exceeded(): void
    {
        $limiterA = new RateLimiter(
            storagePath: $this->tmpPath(),
            maxConcurrent: 1,
            maxPerMinute: 0,
            ipAddress: '127.0.0.1',
        );

        $limiterA->acquire();

        $limiterB = new RateLimiter(
            storagePath: $this->tmpPath(),
            maxConcurrent: 1,
            maxPerMinute: 0,
            ipAddress: '127.0.0.1',
        );

        try {
            $this->expectException(RateLimitExceededException::class);
            $limiterB->acquire();
        } finally {
            $limiterA->release();
        }
    }

    public function test_release_frees_concurrent_slot(): void
    {
        $storage = $this->tmpPath();

        $limiterA = new RateLimiter(
            storagePath: $storage,
            maxConcurrent: 1,
            maxPerMinute: 0,
            ipAddress: '127.0.0.1',
        );

        $limiterA->acquire();
        $limiterA->release();

        $limiterB = new RateLimiter(
            storagePath: $storage,
            maxConcurrent: 1,
            maxPerMinute: 0,
            ipAddress: '127.0.0.1',
        );

        $limiterB->acquire();
        $limiterB->release();

        $this->assertTrue(true);
    }
}