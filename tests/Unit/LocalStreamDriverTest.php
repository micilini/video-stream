<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Drivers\LocalStreamDriver;
use Micilini\VideoStream\Exceptions\VideoNotFoundException;
use Micilini\VideoStream\Tests\TestCase;

final class LocalStreamDriverTest extends TestCase
{
    public function test_constructor_throws_on_missing_file(): void
    {
        $this->expectException(VideoNotFoundException::class);
        $this->expectExceptionMessage('Video not found');

        new LocalStreamDriver('/nonexistent/path/video.mp4');
    }

    public function test_get_size_returns_file_size(): void
    {
        $path = $this->fixturePath('sample.mp4');
        $driver = new LocalStreamDriver($path);

        $size = $driver->getSize();

        $this->assertNotNull($size);
        $this->assertSame(filesize($path), $size);
        $this->assertGreaterThan(0, $size);
    }

    public function test_stream_outputs_correct_bytes(): void
    {
        $path = $this->fixturePath('sample.mp4');
        $driver = new LocalStreamDriver($path);
        $size = $driver->getSize();

        ob_start();
        $driver->stream(0, $size - 1, 4096);
        $output = ob_get_clean();

        $this->assertSame($size, strlen($output));
        $this->assertSame(file_get_contents($path), $output);

        $driver->close();
    }

    public function test_stream_partial_range(): void
    {
        $path = $this->fixturePath('sample.mp4');
        $driver = new LocalStreamDriver($path);


        ob_start();
        $driver->stream(10, 19, 4096);
        $output = ob_get_clean();

        $this->assertSame(10, strlen($output));

        $expected = file_get_contents($path, false, null, 10, 10);
        $this->assertSame($expected, $output);

        $driver->close();
    }

    public function test_stream_from_middle_of_file(): void
    {
        $path = $this->fixturePath('sample.mp4');
        $driver = new LocalStreamDriver($path);
        $size = $driver->getSize();

        $start = (int) floor($size / 2);
        $end = $size - 1;
        $expectedLength = $end - $start + 1;

        ob_start();
        $driver->stream($start, $end, 4096);
        $output = ob_get_clean();

        $this->assertSame($expectedLength, strlen($output));

        $driver->close();
    }

    public function test_stream_with_small_buffer(): void
    {
        $path = $this->fixturePath('sample.mp4');
        $driver = new LocalStreamDriver($path);


        ob_start();
        $driver->stream(0, 99, 16);
        $output = ob_get_clean();

        $this->assertSame(100, strlen($output));

        $expected = file_get_contents($path, false, null, 0, 100);
        $this->assertSame($expected, $output);

        $driver->close();
    }

    public function test_close_is_idempotent(): void
    {
        $path = $this->fixturePath('sample.mp4');
        $driver = new LocalStreamDriver($path);

        $driver->close();
        $driver->close();

        $this->assertTrue(true);
    }
}
