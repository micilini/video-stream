<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Events;

final class CacheExpired extends StreamEvent
{
    public function __construct(array $context = [])
    {
        parent::__construct('onCacheExpired', $context);
    }
}