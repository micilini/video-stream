<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Contracts;

use Micilini\VideoStream\Events\StreamEvent;

interface EventDispatcherInterface
{
    public function dispatch(StreamEvent $event): void;
}