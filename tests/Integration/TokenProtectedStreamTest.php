<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Integration;

use Micilini\VideoStream\VideoStream;
use Micilini\VideoStream\Exceptions\InvalidTokenException;
use Micilini\VideoStream\Tests\TestCase;

final class TokenProtectedStreamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_SERVER['HTTP_RANGE']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_RANGE']);
        parent::tearDown();
    }

    public function test_local_output_works_with_valid_token(): void
    {
        $path = $this->fixturePath('sample.mp4');
        $token = VideoStream::generateToken(
            videoId: 'sample-video',
            secret: 'my-secret',
            expiresIn: 3600,
        );

        $stream = new VideoStream();

        ob_start();

        try {
            $stream
                ->local($path)
                ->withToken(
                    token: $token,
                    secret: 'my-secret',
                    videoId: 'sample-video',
                )
                ->contentType('video/mp4')
                ->output();
        } finally {
            ob_end_clean();
        }

        $this->assertTrue(true);
    }

    public function test_local_output_fails_with_invalid_token(): void
    {
        $path = $this->fixturePath('sample.mp4');
        $stream = new VideoStream();

        $this->expectException(InvalidTokenException::class);

        $stream
            ->local($path)
            ->withToken(
                token: 'invalid-token',
                secret: 'my-secret',
                videoId: 'sample-video',
            )
            ->contentType('video/mp4')
            ->output();
    }
}