<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests\Unit;

use Micilini\VideoStream\Auth\TokenGenerator;
use Micilini\VideoStream\Tests\TestCase;

final class TokenGeneratorTest extends TestCase
{
    public function test_generates_token_with_two_segments(): void
    {
        $token = TokenGenerator::generate(
            videoId: 'movie-123',
            secret: 'my-secret',
            expiresIn: 3600,
        );

        $this->assertStringContainsString('.', $token);

        $parts = explode('.', $token);

        $this->assertCount(2, $parts);
        $this->assertNotEmpty($parts[0]);
        $this->assertNotEmpty($parts[1]);
    }

    public function test_generates_token_with_ip_binding(): void
    {
        $token = TokenGenerator::generate(
            videoId: 'movie-123',
            secret: 'my-secret',
            expiresIn: 3600,
            ip: '127.0.0.1',
        );

        $this->assertStringContainsString('.', $token);
    }
}