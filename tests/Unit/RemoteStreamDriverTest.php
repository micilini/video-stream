<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Drivers\RemoteStreamDriver;
use Micilini\VideoStream\Exceptions\StreamException;
use Micilini\VideoStream\Tests\TestCase;

final class RemoteStreamDriverTest extends TestCase
{
    public function test_constructor_throws_on_invalid_url(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Invalid URL');

        new RemoteStreamDriver('not-a-valid-url');
    }

    public function test_constructor_throws_on_empty_url(): void
    {
        $this->expectException(StreamException::class);

        new RemoteStreamDriver('');
    }

    public function test_constructor_accepts_valid_http_url(): void
    {
        $driver = new RemoteStreamDriver('http://example.com/video.mp4');
        $this->assertInstanceOf(RemoteStreamDriver::class, $driver);
    }

    public function test_constructor_accepts_valid_https_url(): void
    {
        $driver = new RemoteStreamDriver('https://example.com/video.mp4');
        $this->assertInstanceOf(RemoteStreamDriver::class, $driver);
    }

    public function test_close_is_safe_to_call(): void
    {
        $driver = new RemoteStreamDriver('https://example.com/video.mp4');

        $driver->close();
        $driver->close();

        $this->assertTrue(true);
    }
}
