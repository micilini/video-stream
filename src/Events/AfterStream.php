<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Events;

final class AfterStream extends StreamEvent
{
    public function __construct(array $context = [])
    {
        parent::__construct('afterStream', $context);
    }
}