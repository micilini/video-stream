<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Subtitles\SubtitleHandler;
use Micilini\VideoStream\Tests\TestCase;

final class SubtitleHandlerTest extends TestCase
{
    public function test_outputs_vtt_subtitle_file(): void
    {
        $path = $this->fixturePath('sample.vtt');

        $this->expectOutputRegex('/WEBVTT/');

        SubtitleHandler::output($path);
    }
}