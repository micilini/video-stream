<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Events;

final class StreamError extends StreamEvent
{
    public function __construct(array $context = [])
    {
        parent::__construct('onError', $context);
    }
}