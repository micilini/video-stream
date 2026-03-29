<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Integration;

use Micilini\VideoStream\VideoStream;
use Micilini\VideoStream\Exceptions\VideoNotFoundException;
use Micilini\VideoStream\Exceptions\InvalidConfigException;
use Micilini\VideoStream\Tests\TestCase;

final class LocalStreamTest extends TestCase
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

    public function test_local_throws_on_missing_file(): void
    {
        $stream = new VideoStream();

        $this->expectException(VideoNotFoundException::class);
        $stream->local('/nonexistent/file.mp4');
    }

    public function test_output_without_source_throws(): void
    {
        $stream = new VideoStream();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('No video source configured');

        $stream->output();
    }

    public function test_fluent_api_returns_self(): void
    {
        $stream = new VideoStream();
        $path = $this->fixturePath('sample.mp4');

        $result = $stream->local($path);
        $this->assertInstanceOf(VideoStream::class, $result);

        $result2 = $result->contentType('video/webm');
        $this->assertInstanceOf(VideoStream::class, $result2);

        $result3 = $result2->buffer(524288);
        $this->assertInstanceOf(VideoStream::class, $result3);
    }

    public function test_cache_on_local_is_ignored(): void
    {
        $stream = new VideoStream();
        $path = $this->fixturePath('sample.mp4');

        $result = $stream->local($path)->cache(enabled: true, path: $this->tmpPath());

        $this->assertInstanceOf(VideoStream::class, $result);
    }
}
