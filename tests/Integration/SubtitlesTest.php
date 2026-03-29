<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Integration;

use Micilini\VideoStream\VideoStream;
use Micilini\VideoStream\Tests\TestCase;

final class SubtitlesTest extends TestCase
{
    public function test_video_stream_accepts_attached_subtitles(): void
    {
        $path = $this->fixturePath('sample.mp4');

        $stream = new VideoStream();

        $stream
            ->local($path)
            ->subtitles([
                'pt' => '/subtitles/pt.vtt',
                'en' => '/subtitles/en.vtt',
            ])
            ->contentType('video/mp4');

        $this->assertSame([
            'pt' => '/subtitles/pt.vtt',
            'en' => '/subtitles/en.vtt',
        ], $stream->getSubtitles());
    }
}