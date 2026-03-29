<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Auth;

final class TokenGenerator
{
    public static function generate(
        string $videoId,
        string $secret,
        int $expiresIn,
        ?string $ip = null,
    ): string {
        $now = time();

        $payload = [
            'vid' => $videoId,
            'exp' => $now + $expiresIn,
            'iat' => $now,
        ];

        if ($ip !== null && $ip !== '') {
            $payload['ip'] = $ip;
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($payloadJson === false) {
            throw new \RuntimeException('Unable to encode token payload.');
        }

        $encodedPayload = self::base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $encodedPayload, $secret, true);
        $encodedSignature = self::base64UrlEncode($signature);

        return $encodedPayload . '.' . $encodedSignature;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}