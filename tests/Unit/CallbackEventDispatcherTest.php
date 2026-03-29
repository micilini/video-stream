<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Events\BeforeStream;
use Micilini\VideoStream\Events\CallbackEventDispatcher;
use Micilini\VideoStream\Tests\TestCase;

final class CallbackEventDispatcherTest extends TestCase
{
    public function test_dispatches_registered_listener(): void
    {
        $dispatcher = new CallbackEventDispatcher();
        $called = false;
        $receivedContext = [];

        $dispatcher->on('beforeStream', function (array $context) use (&$called, &$receivedContext): void {
            $called = true;
            $receivedContext = $context;
        });

        $dispatcher->dispatch(new BeforeStream([
            'source' => '/tmp/sample.mp4',
            'isRemote' => false,
        ]));

        $this->assertTrue($called);
        $this->assertSame('/tmp/sample.mp4', $receivedContext['source']);
        $this->assertFalse($receivedContext['isRemote']);
    }

    public function test_ignores_non_registered_event(): void
    {
        $dispatcher = new CallbackEventDispatcher();
        $called = false;

        $dispatcher->on('afterStream', function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new BeforeStream([
            'source' => '/tmp/sample.mp4',
        ]));

        $this->assertFalse($called);
    }
}