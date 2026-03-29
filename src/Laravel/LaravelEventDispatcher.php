<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use Micilini\VideoStream\Contracts\EventDispatcherInterface;
use Micilini\VideoStream\Events\StreamEvent;

final class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function dispatch(StreamEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}