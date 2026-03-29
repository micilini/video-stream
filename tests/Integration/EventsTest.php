<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Integration;

use Micilini\VideoStream\VideoStream;
use Micilini\VideoStream\Exceptions\InvalidConfigException;
use Micilini\VideoStream\Tests\TestCase;

final class EventsTest extends TestCase
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

    public function test_local_output_dispatches_before_and_after_events(): void
    {
        $path = $this->fixturePath('sample.mp4');
        $events = [];

        $stream = new VideoStream();

        $stream->on('beforeStream', function (array $context) use (&$events): void {
            $events[] = [
                'name' => 'beforeStream',
                'context' => $context,
            ];
        });

        $stream->on('afterStream', function (array $context) use (&$events): void {
            $events[] = [
                'name' => 'afterStream',
                'context' => $context,
            ];
        });

        ob_start();

        try {
            $stream
                ->local($path)
                ->contentType('video/mp4')
                ->output();
        } finally {
            ob_end_clean();
        }

        $this->assertCount(2, $events);
        $this->assertSame('beforeStream', $events[0]['name']);
        $this->assertSame('afterStream', $events[1]['name']);
        $this->assertSame($path, $events[0]['context']['source']);
        $this->assertSame('video/mp4', $events[1]['context']['contentType']);
        $this->assertIsInt($events[1]['context']['durationMs']);
    }

    public function test_output_without_source_dispatches_error_event(): void
    {
        $events = [];
        $stream = new VideoStream();

        $stream->on('onError', function (array $context) use (&$events): void {
            $events[] = $context;
        });

        try {
            $stream->output();
            $this->fail('Expected InvalidConfigException was not thrown.');
        } catch (InvalidConfigException $e) {
            $this->assertCount(1, $events);
            $this->assertStringContainsString('No video source configured', $events[0]['message']);
        }
    }
}