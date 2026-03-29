<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Laravel;

use Illuminate\Support\Facades\Facade;

class VideoStreamFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Micilini\VideoStream\VideoStream::class;
    }
}