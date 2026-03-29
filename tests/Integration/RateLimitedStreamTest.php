<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Integration;

use Micilini\VideoStream\Exceptions\RateLimitExceededException;
use Micilini\VideoStream\VideoStream;
use Micilini\VideoStream\Tests\TestCase;

final class RateLimitedStreamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_SERVER['HTTP_RANGE']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_RANGE']);
        parent::tearDown();
    }

    public function test_local_output_works_with_rate_limit_enabled(): void
    {
        $path = $this->fixturePath('sample.mp4');
        $stream = new VideoStream();

        ob_start();

        try {
            $stream
                ->local($path)
                ->rateLimit(
                    maxConcurrent: 1,
                    maxPerMinute: 10,
                    storagePath: $this->tmpPath('rate-limit-ok'),
                    ip: '127.0.0.1',
                )
                ->contentType('video/mp4')
                ->output();
        } finally {
            ob_end_clean();
        }

        $this->assertTrue(true);
    }

    public function test_rate_limit_blocks_second_request_with_same_ip(): void
    {
        $storage = $this->tmpPath('rate-limit-block');
        $path = $this->fixturePath('sample.mp4');

        $streamA = new VideoStream();
        $streamB = new VideoStream();

        $reflection = new \ReflectionClass($streamA);
        $method = $reflection->getMethod('acquireRateLimit');
        $method->setAccessible(true);

        $streamA
            ->local($path)
            ->rateLimit(
                maxConcurrent: 1,
                maxPerMinute: 10,
                storagePath: $storage,
                ip: '127.0.0.1',
            );

        $streamB
            ->local($path)
            ->rateLimit(
                maxConcurrent: 1,
                maxPerMinute: 10,
                storagePath: $storage,
                ip: '127.0.0.1',
            );

        $method->invoke($streamA);

        try {
            $this->expectException(RateLimitExceededException::class);
            $streamB->output();
        } finally {
            $release = $reflection->getMethod('releaseRateLimit');
            $release->setAccessible(true);
            $release->invoke($streamA);
        }
    }
}