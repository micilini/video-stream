<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Events;

use Micilini\VideoStream\Contracts\EventDispatcherInterface;

final class CallbackEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, array<int, callable>>
     */
    private array $listeners = [];

    public function on(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName] ??= [];
        $this->listeners[$eventName][] = $listener;
    }

    public function dispatch(StreamEvent $event): void
    {
        foreach ($this->listeners[$event->name] ?? [] as $listener) {
            $listener($event->all(), $event);
        }
    }
}