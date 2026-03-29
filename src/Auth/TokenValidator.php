<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Auth;

use Micilini\VideoStream\Exceptions\InvalidTokenException;
use Micilini\VideoStream\Exceptions\TokenExpiredException;

final class TokenValidator
{
    public static function validate(
        string $token,
        string $secret,
        string $videoId,
        ?string $ip = null,
    ): array {
        if (!str_contains($token, '.')) {
            throw new InvalidTokenException('Malformed token.');
        }

        [$encodedPayload, $encodedSignature] = explode('.', $token, 2);

        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', $encodedPayload, $secret, true)
        );

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            throw new InvalidTokenException('Invalid token signature.');
        }

        $payloadJson = self::base64UrlDecode($encodedPayload);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            throw new InvalidTokenException('Invalid token payload.');
        }

        if (($payload['vid'] ?? null) !== $videoId) {
            throw new InvalidTokenException('Token video identifier mismatch.');
        }

        $expiresAt = (int) ($payload['exp'] ?? 0);

        if ($expiresAt <= 0 || time() > $expiresAt) {
            throw new TokenExpiredException('Token has expired.');
        }

        if (array_key_exists('ip', $payload)) {
            if ($ip === null || $ip === '' || $payload['ip'] !== $ip) {
                throw new InvalidTokenException('Token IP mismatch.');
            }
        }

        return $payload;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidTokenException('Invalid base64url token payload.');
        }

        return $decoded;
    }
}