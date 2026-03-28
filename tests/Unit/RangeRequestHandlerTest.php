<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Config\StreamConfig;
use Micilini\VideoStream\Http\RangeRequestHandler;
use Micilini\VideoStream\Tests\TestCase;

final class RangeRequestHandlerTest extends TestCase
{
    private StreamConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new StreamConfig();

        unset($_SERVER['HTTP_RANGE']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_RANGE']);
        parent::tearDown();
    }

    public function test_no_range_returns_full_content(): void
    {
        $handler = new RangeRequestHandler(1000, $this->config);

        $this->assertSame(200, $handler->getStatusCode());
        $this->assertSame(0, $handler->getStart());
        $this->assertSame(999, $handler->getEnd());
        $this->assertSame('1000', $handler->getHeaders()['Content-Length']);
        $this->assertSame('bytes', $handler->getHeaders()['Accept-Ranges']);
    }

    public function test_range_from_start(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-499';

        $handler = new RangeRequestHandler(1000, $this->config);

        $this->assertSame(206, $handler->getStatusCode());
        $this->assertSame(0, $handler->getStart());
        $this->assertSame(499, $handler->getEnd());
        $this->assertSame('500', $handler->getHeaders()['Content-Length']);
        $this->assertSame('bytes 0-499/1000', $handler->getHeaders()['Content-Range']);
    }

    public function test_range_from_middle(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=500-749';

        $handler = new RangeRequestHandler(1000, $this->config);

        $this->assertSame(206, $handler->getStatusCode());
        $this->assertSame(500, $handler->getStart());
        $this->assertSame(749, $handler->getEnd());
        $this->assertSame('250', $handler->getHeaders()['Content-Length']);
        $this->assertSame('bytes 500-749/1000', $handler->getHeaders()['Content-Range']);
    }

    public function test_range_open_ended(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=800-';

        $handler = new RangeRequestHandler(1000, $this->config);

        $this->assertSame(206, $handler->getStatusCode());
        $this->assertSame(800, $handler->getStart());
        $this->assertSame(999, $handler->getEnd());
        $this->assertSame('200', $handler->getHeaders()['Content-Length']);
        $this->assertSame('bytes 800-999/1000', $handler->getHeaders()['Content-Range']);
    }

    public function test_range_clamps_end_to_file_size(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9999';

        $handler = new RangeRequestHandler(1000, $this->config);

        $this->assertSame(206, $handler->getStatusCode());
        $this->assertSame(0, $handler->getStart());
        $this->assertSame(999, $handler->getEnd());
    }

    public function test_headers_include_content_type(): void
    {
        $config = new StreamConfig(contentType: 'video/webm');
        $handler = new RangeRequestHandler(1000, $config);

        $this->assertSame('video/webm', $handler->getHeaders()['Content-Type']);
    }

    public function test_headers_include_cache_control(): void
    {
        $handler = new RangeRequestHandler(1000, $this->config);

        $this->assertSame('max-age=2592000, public', $handler->getHeaders()['Cache-Control']);
    }

    public function test_large_file_size(): void
    {
        $size = 4_294_967_296;
        $_SERVER['HTTP_RANGE'] = 'bytes=1000000-1999999';

        $handler = new RangeRequestHandler($size, $this->config);

        $this->assertSame(206, $handler->getStatusCode());
        $this->assertSame(1000000, $handler->getStart());
        $this->assertSame(1999999, $handler->getEnd());
        $this->assertSame('1000000', $handler->getHeaders()['Content-Length']);
    }
}
