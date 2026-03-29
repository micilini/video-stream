<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Auth\TokenGenerator;
use Micilini\VideoStream\Auth\TokenValidator;
use Micilini\VideoStream\Exceptions\InvalidTokenException;
use Micilini\VideoStream\Exceptions\TokenExpiredException;
use Micilini\VideoStream\Tests\TestCase;

final class TokenValidatorTest extends TestCase
{
    public function test_validates_fresh_token(): void
    {
        $token = TokenGenerator::generate(
            videoId: 'movie-123',
            secret: 'my-secret',
            expiresIn: 3600,
        );

        $payload = TokenValidator::validate(
            token: $token,
            secret: 'my-secret',
            videoId: 'movie-123',
        );

        $this->assertSame('movie-123', $payload['vid']);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('iat', $payload);
    }

    public function test_rejects_token_with_wrong_video_id(): void
    {
        $token = TokenGenerator::generate(
            videoId: 'movie-123',
            secret: 'my-secret',
            expiresIn: 3600,
        );

        $this->expectException(InvalidTokenException::class);

        TokenValidator::validate(
            token: $token,
            secret: 'my-secret',
            videoId: 'another-video',
        );
    }

    public function test_rejects_expired_token(): void
    {
        $token = TokenGenerator::generate(
            videoId: 'movie-123',
            secret: 'my-secret',
            expiresIn: -10,
        );

        $this->expectException(TokenExpiredException::class);

        TokenValidator::validate(
            token: $token,
            secret: 'my-secret',
            videoId: 'movie-123',
        );
    }

    public function test_rejects_ip_mismatch(): void
    {
        $token = TokenGenerator::generate(
            videoId: 'movie-123',
            secret: 'my-secret',
            expiresIn: 3600,
            ip: '127.0.0.1',
        );

        $this->expectException(InvalidTokenException::class);

        TokenValidator::validate(
            token: $token,
            secret: 'my-secret',
            videoId: 'movie-123',
            ip: '10.0.0.1',
        );
    }
}