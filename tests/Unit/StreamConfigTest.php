<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Config\StreamConfig;
use Micilini\VideoStream\Exceptions\InvalidConfigException;
use Micilini\VideoStream\Tests\TestCase;

final class StreamConfigTest extends TestCase
{
    public function test_default_values(): void
    {
        $config = new StreamConfig();

        $this->assertSame(262144, $config->bufferSize);
        $this->assertSame('video/mp4', $config->contentType);
        $this->assertSame('max-age=2592000, public', $config->cacheControl);
        $this->assertNotEmpty($config->expires);
        $this->assertNotEmpty($config->lastModified);
    }

    public function test_custom_values(): void
    {
        $config = new StreamConfig(
            bufferSize: 524288,
            contentType: 'video/webm',
            cacheControl: 'no-cache',
            expires: 'Thu, 01 Jan 2030 00:00:00 GMT',
            lastModified: 'Mon, 01 Jan 2024 00:00:00 GMT',
        );

        $this->assertSame(524288, $config->bufferSize);
        $this->assertSame('video/webm', $config->contentType);
        $this->assertSame('no-cache', $config->cacheControl);
        $this->assertSame('Thu, 01 Jan 2030 00:00:00 GMT', $config->expires);
        $this->assertSame('Mon, 01 Jan 2024 00:00:00 GMT', $config->lastModified);
    }

    public function test_buffer_too_small_throws(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Buffer size must be at least 1024 bytes');

        new StreamConfig(bufferSize: 512);
    }

    public function test_buffer_exactly_1024_is_valid(): void
    {
        $config = new StreamConfig(bufferSize: 1024);
        $this->assertSame(1024, $config->bufferSize);
    }

    public function test_last_modified_can_be_defined_manually(): void
    {
        $config = new StreamConfig(
            lastModified: 'Mon, 01 Jan 2024 00:00:00 GMT',
        );

        $this->assertSame('Mon, 01 Jan 2024 00:00:00 GMT', $config->lastModified);
    }

    public function test_default_last_modified_is_generated_in_gmt(): void
    {
        $config = new StreamConfig();

        $this->assertNotEmpty($config->lastModified);
        $this->assertStringContainsString('GMT', $config->lastModified);
    }

    public function test_immutability(): void
    {
        $config = new StreamConfig();

        $this->assertInstanceOf(StreamConfig::class, $config);
    }
}
