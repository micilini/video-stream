<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Events;

final class BeforeStream extends StreamEvent
{
    public function __construct(array $context = [])
    {
        parent::__construct('beforeStream', $context);
    }
}