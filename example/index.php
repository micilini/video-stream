<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Micilini\VideoStream\Subtitles\SubtitleHandler;
use Micilini\VideoStream\VideoStream;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Range, Content-Type, Origin, Accept');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges');
header('Access-Control-Allow-Methods: GET');

$action = $_GET['action'] ?? null;
$eventLogPath = __DIR__ . '/event-log.json';
$cachePath = __DIR__ . '/cache';
$revokedTokensPath = __DIR__ . '/revoked-tokens.json';
$rateLimitPath = __DIR__ . '/rate-limit';
$subtitlePath = __DIR__ . '/subtitles';
$exampleSecret = 'video-stream-example-secret';

if (!is_dir($cachePath)) {
    mkdir($cachePath, 0777, true);
}

if (!is_dir($rateLimitPath)) {
    mkdir($rateLimitPath, 0777, true);
}

if (!is_dir($subtitlePath)) {
    mkdir($subtitlePath, 0777, true);
}

$demoPtVtt = $subtitlePath . DIRECTORY_SEPARATOR . 'sample-pt.vtt';
$demoEnVtt = $subtitlePath . DIRECTORY_SEPARATOR . 'sample-en.vtt';

if (!is_file($demoPtVtt)) {
    file_put_contents(
        $demoPtVtt,
        "WEBVTT\n\n00:00:00.000 --> 00:00:03.000\nLegenda de teste em português\n\n00:00:03.000 --> 00:00:06.000\nSegunda linha da legenda PT-BR\n"
    );
}

if (!is_file($demoEnVtt)) {
    file_put_contents(
        $demoEnVtt,
        "WEBVTT\n\n00:00:00.000 --> 00:00:03.000\nEnglish subtitle test\n\n00:00:03.000 --> 00:00:06.000\nSecond subtitle line in English\n"
    );
}

if (!is_file($revokedTokensPath)) {
    file_put_contents($revokedTokensPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$normalizeValue = function (mixed $value): mixed {
    if (is_scalar($value) || $value === null) {
        return $value;
    }

    if ($value instanceof Throwable) {
        return [
            'class' => $value::class,
            'message' => $value->getMessage(),
        ];
    }

    if (is_array($value)) {
        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = is_object($item)
                ? ['class' => $item::class]
                : (is_array($item) ? $item : $item);
        }

        return $normalized;
    }

    if (is_object($value)) {
        return ['class' => $value::class];
    }

    return (string) $value;
};

$requestId = bin2hex(random_bytes(8));

$appendEvent = function (string $eventName, array $context) use ($eventLogPath, $normalizeValue, $requestId): void {
    $rows = [];

    if (is_file($eventLogPath)) {
        $decoded = json_decode((string) file_get_contents($eventLogPath), true);

        if (is_array($decoded)) {
            $rows = $decoded;
        }
    }

    $normalizedContext = [];

    foreach ($context as $key => $value) {
        $normalizedContext[$key] = $normalizeValue($value);
    }

    $rows[] = [
        'requestId' => $requestId,
        'event' => $eventName,
        'time' => gmdate('Y-m-d H:i:s'),
        'context' => $normalizedContext,
    ];

    file_put_contents(
        $eventLogPath,
        json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
};

$isTokenRevoked = function (string $token) use ($revokedTokensPath): bool {
    if ($token === '' || !is_file($revokedTokensPath)) {
        return false;
    }

    $decoded = json_decode((string) file_get_contents($revokedTokensPath), true);

    if (!is_array($decoded)) {
        return false;
    }

    return in_array($token, $decoded, true);
};

$revokeToken = function (string $token) use ($revokedTokensPath): void {
    if ($token === '') {
        return;
    }

    $decoded = json_decode((string) file_get_contents($revokedTokensPath), true);

    if (!is_array($decoded)) {
        $decoded = [];
    }

    if (!in_array($token, $decoded, true)) {
        $decoded[] = $token;
    }

    file_put_contents(
        $revokedTokensPath,
        json_encode(array_values($decoded), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
};

$resolveLocalVideoPath = function (string $file): ?string {
    $safeFile = basename($file);

    $candidates = [
        __DIR__ . '/videos/' . $safeFile,
        dirname(__DIR__) . '/tests/fixtures/' . $safeFile,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
};

$maxConcurrent = max(0, (int) ($_GET['maxConcurrent'] ?? 1));
$maxPerMinute = max(0, (int) ($_GET['maxPerMinute'] ?? 2));

if ($action === null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'project' => 'video-stream-example',
        'status' => 'ok',
        'routes' => [
            'local' => '/video-stream/example/index.php?action=local&file=sample.mp4',
            'local_with_subtitles' => '/video-stream/example/index.php?action=local-with-subtitles&file=sample.mp4',
            'subtitle_file_pt' => '/video-stream/example/index.php?action=subtitle&lang=pt',
            'subtitle_file_en' => '/video-stream/example/index.php?action=subtitle&lang=en',
            'subtitles' => '/video-stream/example/index.php?action=subtitles',
            'remote_no_cache' => '/video-stream/example/index.php?action=remote-no-cache&url=https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            'remote_cache' => '/video-stream/example/index.php?action=remote-cache&url=https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            'remote_token' => '/video-stream/example/index.php?action=remote-token&url=https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            'remote_cache_token' => '/video-stream/example/index.php?action=remote-cache-token&url=https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            'remote_rate_limit' => '/video-stream/example/index.php?action=remote-rate-limit&url=https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4&maxConcurrent=1&maxPerMinute=2',
            'remote_cache_rate_limit' => '/video-stream/example/index.php?action=remote-cache-rate-limit&url=https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4&maxConcurrent=1&maxPerMinute=2',
            'remote_token_rate_limit' => '/video-stream/example/index.php?action=remote-token-rate-limit&url=https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4&videoId=demo-video&token=YOUR_TOKEN&maxConcurrent=1&maxPerMinute=2',
            'generate_token' => '/video-stream/example/index.php?action=generate-token&videoId=demo-video',
            'revoke_token' => '/video-stream/example/index.php?action=revoke-token',
            'clear_revoked_tokens' => '/video-stream/example/index.php?action=clear-revoked-tokens',
            'events' => '/video-stream/example/index.php?action=events',
            'clear_events' => '/video-stream/example/index.php?action=clear-events',
            'remote_no_cache_with_subtitles' => '/video-stream/example/index.php?action=remote-no-cache-with-subtitles&url=https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            'remote_cache_with_subtitles' => '/video-stream/example/index.php?action=remote-cache-with-subtitles&url=https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'subtitle') {
    $lang = trim((string) ($_GET['lang'] ?? 'pt'));

    $map = [
        'pt' => $demoPtVtt,
        'en' => $demoEnVtt,
    ];

    if (!isset($map[$lang])) {
        http_response_code(404);
        echo 'Subtitle not found.';
        exit;
    }

    SubtitleHandler::output($map[$lang]);
    exit;
}

if ($action === 'subtitles') {
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => 'ok',
        'tracks' => [
            [
                'srclang' => 'pt',
                'label' => 'Português',
                'src' => '/video-stream/example/index.php?action=subtitle&lang=pt',
                'default' => true,
            ],
            [
                'srclang' => 'en',
                'label' => 'English',
                'src' => '/video-stream/example/index.php?action=subtitle&lang=en',
                'default' => false,
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'events') {
    header('Content-Type: application/json; charset=utf-8');

    if (!is_file($eventLogPath)) {
        echo json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo (string) file_get_contents($eventLogPath);
    exit;
}

if ($action === 'generate-token') {
    header('Content-Type: application/json; charset=utf-8');

    $videoId = trim((string) ($_GET['videoId'] ?? ''));
    $expiresIn = (int) ($_GET['expiresIn'] ?? 3600);
    $bindIp = (string) ($_GET['bindIp'] ?? '0');

    if ($videoId === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing "videoId" parameter.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $ip = null;

    if ($bindIp === '1') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    }

    $token = VideoStream::generateToken(
        videoId: $videoId,
        secret: $exampleSecret,
        expiresIn: $expiresIn,
        ip: $ip,
    );

    echo json_encode([
        'status' => 'ok',
        'videoId' => $videoId,
        'expiresIn' => $expiresIn,
        'ipBound' => $ip !== null,
        'token' => $token,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'revoke-token') {
    header('Content-Type: application/json; charset=utf-8');

    $token = trim((string) ($_GET['token'] ?? ''));

    if ($token === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing "token" parameter.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $revokeToken($token);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Token revoked successfully.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'clear-revoked-tokens') {
    header('Content-Type: application/json; charset=utf-8');

    file_put_contents($revokedTokensPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    echo json_encode([
        'status' => 'ok',
        'message' => 'Revoked token list cleared.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'clear-events') {
    header('Content-Type: application/json; charset=utf-8');

    if (is_file($eventLogPath)) {
        @unlink($eventLogPath);
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Event log cleared.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $stream = new VideoStream();
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;

    foreach (['beforeStream', 'afterStream', 'onCacheHit', 'onCacheMiss', 'onCacheExpired', 'onError'] as $eventName) {
        $stream->on($eventName, function (array $context) use ($appendEvent, $eventName): void {
            $appendEvent($eventName, $context);
        });
    }

    switch ($action) {
        case 'local':
            $file = trim((string) ($_GET['file'] ?? ''));

            if ($file === '') {
                http_response_code(400);
                echo 'Missing "file" parameter.';
                exit;
            }

            $fullPath = $resolveLocalVideoPath($file);

            if ($fullPath === null) {
                http_response_code(404);
                echo 'Local video not found: ' . htmlspecialchars(basename($file), ENT_QUOTES, 'UTF-8');
                exit;
            }

            $stream
                ->local($fullPath)
                ->subtitles([
                    'pt' => '/video-stream/example/index.php?action=subtitle&lang=pt',
                    'en' => '/video-stream/example/index.php?action=subtitle&lang=en',
                ])
                ->contentType('video/mp4')
                ->output();
            exit;

        case 'local-with-subtitles':
            $file = trim((string) ($_GET['file'] ?? ''));

            if ($file === '') {
                http_response_code(400);
                echo 'Missing "file" parameter.';
                exit;
            }

            $fullPath = $resolveLocalVideoPath($file);

            if ($fullPath === null) {
                http_response_code(404);
                echo 'Local video not found: ' . htmlspecialchars(basename($file), ENT_QUOTES, 'UTF-8');
                exit;
            }

            $stream
                ->local($fullPath)
                ->subtitles([
                    'pt' => '/video-stream/example/index.php?action=subtitle&lang=pt',
                    'en' => '/video-stream/example/index.php?action=subtitle&lang=en',
                ])
                ->contentType('video/mp4')
                ->output();
            exit;

        case 'remote-no-cache-with-subtitles':
            $url = trim((string) ($_GET['url'] ?? ''));

            if ($url === '') {
                http_response_code(400);
                echo 'Missing "url" parameter.';
                exit;
            }

            $stream
                ->remote($url)
                ->subtitles([
                    'pt' => '/video-stream/example/index.php?action=subtitle&lang=pt',
                    'en' => '/video-stream/example/index.php?action=subtitle&lang=en',
                ])
                ->contentType('video/mp4')
                ->output();
            exit;

        case 'remote-cache-with-subtitles':
            $url = trim((string) ($_GET['url'] ?? ''));

            if ($url === '') {
                http_response_code(400);
                echo 'Missing "url" parameter.';
                exit;
            }

            $stream
                ->remote($url)
                ->subtitles([
                    'pt' => '/video-stream/example/index.php?action=subtitle&lang=pt',
                    'en' => '/video-stream/example/index.php?action=subtitle&lang=en',
                ])
                ->contentType('video/mp4')
                ->cache(
                    enabled: true,
                    ttl: 3600,
                    fresh: false,
                    path: $cachePath,
                    maxDisk: 1073741824
                )
                ->output();
            exit;

        case 'remote-no-cache':
            $url = trim((string) ($_GET['url'] ?? ''));

            if ($url === '') {
                http_response_code(400);
                echo 'Missing "url" parameter.';
                exit;
            }

            $stream
                ->remote($url)
                ->contentType('video/mp4')
                ->output();
            exit;

        case 'remote-cache':
            $url = trim((string) ($_GET['url'] ?? ''));

            if ($url === '') {
                http_response_code(400);
                echo 'Missing "url" parameter.';
                exit;
            }

            $stream
                ->remote($url)
                ->contentType('video/mp4')
                ->cache(
                    enabled: true,
                    ttl: 3600,
                    fresh: false,
                    path: $cachePath,
                    maxDisk: 1073741824
                )
                ->output();
            exit;

        case 'remote-token':
            $url = trim((string) ($_GET['url'] ?? ''));
            $token = trim((string) ($_GET['token'] ?? ''));
            $videoId = trim((string) ($_GET['videoId'] ?? ''));

            if ($url === '') {
                http_response_code(400);
                echo 'Missing "url" parameter.';
                exit;
            }

            if ($token === '' || $videoId === '') {
                http_response_code(400);
                echo 'Missing "token" or "videoId" parameter.';
                exit;
            }

            if ($isTokenRevoked($token)) {
                http_response_code(403);
                echo 'Token has been revoked.';
                exit;
            }

            $stream
                ->remote($url)
                ->withToken(
                    token: $token,
                    secret: $exampleSecret,
                    videoId: $videoId,
                    ip: $clientIp,
                )
                ->contentType('video/mp4')
                ->output();
            exit;

        case 'remote-cache-token':
            $url = trim((string) ($_GET['url'] ?? ''));
            $token = trim((string) ($_GET['token'] ?? ''));
            $videoId = trim((string) ($_GET['videoId'] ?? ''));

            if ($url === '') {
                http_response_code(400);
                echo 'Missing "url" parameter.';
                exit;
            }

            if ($token === '' || $videoId === '') {
                http_response_code(400);
                echo 'Missing "token" or "videoId" parameter.';
                exit;
            }

            if ($isTokenRevoked($token)) {
                http_response_code(403);
                echo 'Token has been revoked.';
                exit;
            }

            $stream
                ->remote($url)
                ->withToken(
                    token: $token,
                    secret: $exampleSecret,
                    videoId: $videoId,
                    ip: $clientIp,
                )
                ->contentType('video/mp4')
                ->cache(
                    enabled: true,
                    ttl: 3600,
                    fresh: false,
                    path: $cachePath,
                    maxDisk: 1073741824
                )
                ->output();
            exit;

        case 'remote-rate-limit':
            $url = trim((string) ($_GET['url'] ?? ''));

            if ($url === '') {
                http_response_code(400);
                echo 'Missing "url" parameter.';
                exit;
            }

            $stream
                ->remote($url)
                ->rateLimit(
                    maxConcurrent: $maxConcurrent,
                    maxPerMinute: $maxPerMinute,
                    storagePath: $rateLimitPath,
                    ip: $clientIp,
                )
                ->contentType('video/mp4')
                ->output();
            exit;

        case 'remote-cache-rate-limit':
            $url = trim((string) ($_GET['url'] ?? ''));

            if ($url === '') {
                http_response_code(400);
                echo 'Missing "url" parameter.';
                exit;
            }

            $stream
                ->remote($url)
                ->rateLimit(
                    maxConcurrent: $maxConcurrent,
                    maxPerMinute: $maxPerMinute,
                    storagePath: $rateLimitPath,
                    ip: $clientIp,
                )
                ->contentType('video/mp4')
                ->cache(
                    enabled: true,
                    ttl: 3600,
                    fresh: false,
                    path: $cachePath,
                    maxDisk: 1073741824
                )
                ->output();
            exit;

        case 'remote-token-rate-limit':
            $url = trim((string) ($_GET['url'] ?? ''));
            $token = trim((string) ($_GET['token'] ?? ''));
            $videoId = trim((string) ($_GET['videoId'] ?? ''));

            if ($url === '') {
                http_response_code(400);
                echo 'Missing "url" parameter.';
                exit;
            }

            if ($token === '' || $videoId === '') {
                http_response_code(400);
                echo 'Missing "token" or "videoId" parameter.';
                exit;
            }

            if ($isTokenRevoked($token)) {
                http_response_code(403);
                echo 'Token has been revoked.';
                exit;
            }

            $stream
                ->remote($url)
                ->withToken(
                    token: $token,
                    secret: $exampleSecret,
                    videoId: $videoId,
                    ip: $clientIp,
                )
                ->rateLimit(
                    maxConcurrent: $maxConcurrent,
                    maxPerMinute: $maxPerMinute,
                    storagePath: $rateLimitPath,
                    ip: $clientIp,
                )
                ->contentType('video/mp4')
                ->output();
            exit;

        default:
            http_response_code(404);
            echo 'Invalid action.';
            exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Stream error: ' . $e->getMessage();
    exit;
}