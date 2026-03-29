<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Events;

final class CacheMiss extends StreamEvent
{
    public function __construct(array $context = [])
    {
        parent::__construct('onCacheMiss', $context);
    }
}