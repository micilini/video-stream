<div align="center">

# 🎬 VideoStream

**PHP video streaming library with HTTP Range support, optional disk cache, and Laravel integration.**

Works standalone (PHP puro) or with Laravel 10/11/12/13+.

[![PHP](https://img.shields.io/badge/PHP-^8.2-8892BF?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012%20|%2013-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/version-2.3.0-blue?style=flat-square)](https://github.com/micilini/video-stream/releases)
[![Tests](https://img.shields.io/github/actions/workflow/status/micilini/video-stream/tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/micilini/video-stream/actions)
[![Packagist](https://img.shields.io/packagist/dt/micilini/video-stream?style=flat-square&label=downloads)](https://packagist.org/packages/micilini/video-stream)

</div>

---

## Features

- **Stream local videos** — serve files from your server with proper HTTP Range support
- **Stream remote videos** — proxy external URLs via cURL with native Range passthrough
- **Disk cache** — optional file-based cache for remote videos (LRU eviction, TTL, stale fallback)
- **HTTP Range Requests** — seek, scrub, and resume — works with any HTML5 player
- **Laravel integration** — Service Provider, Facade, publishable config, Artisan commands
- **Framework agnostic** — works with Laravel, Symfony, Slim, CodeIgniter, or plain PHP
- **Zero external dependencies** — core requires only PHP 8.2+ and ext-curl

---

## Installation

```bash
composer require micilini/video-stream
```

### Requirements

- PHP 8.2 or higher
- ext-curl

---

## Quick Start

### PHP Puro (sem framework)

```php
<?php

require 'vendor/autoload.php';

use Micilini\VideoStream\VideoStream;

// Local video
$stream = new VideoStream();
$stream->local('/var/www/videos/movie.mp4')->output();
```

```php
// Remote video
$stream = new VideoStream();
$stream->remote('https://cdn.example.com/video.mp4')->output();
```

```php
// Remote video with disk cache
$stream = new VideoStream();
$stream->remote('https://cdn.example.com/video.mp4')
    ->cache(enabled: true, ttl: 3600, path: '/tmp/video-cache')
    ->output();
```

### Laravel

After installing via Composer, the Service Provider and Facade are auto-discovered.

**Publish the config (optional):**

```bash
php artisan vendor:publish --tag=video-stream-config
```

**Routes:**

```php
use Micilini\VideoStream\Laravel\VideoStreamFacade as VideoStream;

// Local video
Route::get('/video/{file}', function (string $file) {
    $path = storage_path("app/videos/{$file}");
    return VideoStream::local($path)->stream();
});

// Remote video with cache
Route::get('/stream', function () {
    $url = request('url');
    return VideoStream::remote($url)
        ->cache(enabled: true)
        ->stream(); // returns StreamedResponse automatically
});
```

### Symfony (sem Laravel)

```php
use Micilini\VideoStream\VideoStream;

class VideoController
{
    public function play(): Response
    {
        $stream = new VideoStream();
        return $stream->local('/path/to/video.mp4')->stream(); // returns StreamedResponse
    }
}
```

### Slim / CodeIgniter / outros

```php
use Micilini\VideoStream\VideoStream;

$stream = new VideoStream();
$stream->local('/path/to/video.mp4')->output(); // echo direto
```

---

## API Reference

### `VideoStream` — Main Class

```php
$stream = new VideoStream(
    defaultBufferSize: 262144,      // 256KB (optional)
    defaultContentType: 'video/mp4', // (optional)
    cacheHandler: null,              // CacheHandlerInterface (optional)
);
```

### Fluent Methods

| Method | Description |
|--------|-------------|
| `->local(string $path)` | Set a local file as the video source |
| `->remote(string $url)` | Set a remote URL as the video source |
| `->contentType(string $type)` | Override the Content-Type header |
| `->buffer(int $bytes)` | Override the buffer size |
| `->cacheControl(string $value)` | Override the Cache-Control header |
| `->cache(...)` | Configure disk cache (remote videos only) |
| `->output()` | Stream directly via `echo` + `flush` |
| `->stream()` | Returns `StreamedResponse` if Symfony is available, otherwise calls `output()` |

### Cache Options

```php
$stream->remote($url)->cache(
    enabled: true,           // Enable disk cache
    ttl: 86400,              // Time-to-live in seconds (default: 24h)
    fresh: false,            // Force re-download even if cached
    path: '/tmp/video-cache', // Cache directory (PHP puro)
    maxDisk: 10_737_418_240,  // Max disk usage in bytes (default: 10GB)
)->output();
```

For local videos, `->cache()` is silently ignored.

---

## Disk Cache

When enabled, remote videos are downloaded once and served from disk on subsequent requests. The cache uses LRU eviction when the disk limit is reached.

```
SEM CACHE:    Request → Fetch from remote → Stream to browser (every time)
COM CACHE:    Request → Cached on disk? → Yes → Stream local
                                        → No  → Download → Save → Stream local
```

**Cache behavior:**

| Scenario | Behavior |
|----------|----------|
| Cache hit, not expired | Serve from disk instantly |
| Cache hit, expired, remote OK | Re-download, replace cache |
| Cache hit, expired, remote down | Serve stale cache (better than error) |
| Cache miss, remote OK | Download, cache, serve |
| Cache miss, remote down | Throw `StreamException` |
| Disk full | LRU eviction removes oldest files |

---

## Laravel Configuration

After publishing the config (`php artisan vendor:publish --tag=video-stream-config`), edit `config/video-stream.php`:

```php
return [
    'buffer_size' => 262144,
    'default_content_type' => 'video/mp4',
    'cache' => [
        'enabled' => env('VIDEO_STREAM_CACHE', false),
        'path' => storage_path('app/video-cache'),
        'ttl' => (int) env('VIDEO_STREAM_CACHE_TTL', 86400),
        'max_disk_usage' => (int) env('VIDEO_STREAM_CACHE_MAX_DISK', 10 * 1024 * 1024 * 1024),
    ],
];
```

**.env:**

```
VIDEO_STREAM_CACHE=true
VIDEO_STREAM_CACHE_TTL=86400
VIDEO_STREAM_CACHE_MAX_DISK=10737418240
```

### Artisan Commands

```bash
php artisan video-stream:cache-status           # Show disk usage and stats
php artisan video-stream:cache-clear            # Clear all cached videos
php artisan video-stream:cache-clear --expired  # Clear only expired videos
```

---

## Legacy API (v1.x)

The old API still works but is deprecated and will be removed in v3.0:

```php
// ⚠️ Deprecated — use ->local() / ->remote() instead
$videoStream = new VideoStream();
$videoStream->streamVideo($videoPath, [
    'is_localPath' => true,
    'video_buffer' => 262144,
    'content_type' => 'video/mp4',
]);
```

The `is_https` option has been removed — it was the cause of the 502 Bad Gateway bug in v1.x.

---

## Project Structure

```
src/
├── VideoStream.php                  # Main API (PHP puro)
├── Contracts/
│   ├── StreamDriverInterface.php
│   └── CacheHandlerInterface.php
├── Config/
│   └── StreamConfig.php             # Immutable config value object
├── Drivers/
│   ├── LocalStreamDriver.php        # fopen + fread
│   └── RemoteStreamDriver.php       # cURL with Range passthrough
├── Cache/
│   └── FileCacheHandler.php         # Disk cache with LRU eviction
├── Http/
│   └── RangeRequestHandler.php      # HTTP Range parsing (shared)
├── Exceptions/
│   ├── StreamException.php
│   ├── VideoNotFoundException.php
│   ├── InvalidConfigException.php
│   └── CacheException.php
└── Laravel/
    ├── VideoStreamServiceProvider.php
    ├── VideoStreamFacade.php
    └── Commands/
        ├── CacheClearCommand.php
        └── CacheStatusCommand.php
```

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests cover StreamConfig validation, HTTP Range parsing, local streaming (byte-level accuracy), cache TTL/eviction/flush, and integration with the fluent API.

---

## Upgrading from v1.x

| v1.x | v2.x |
|------|------|
| `streamVideo($path, $options)` | `->local($path)->output()` or `->remote($url)->output()` |
| `is_https => true` | Removed (was causing 502 bug) |
| `is_localPath => true/false` | `->local()` or `->remote()` |
| `video_buffer => 512` | `->buffer(262144)` (default: 256KB) |
| No cache | `->cache(enabled: true)` |
| Laravel only | PHP puro + Laravel + Symfony + any framework |

---

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

---

## License

[MIT](LICENSE)

---

<div align="center">

Made with ☕ by [Micilini](https://github.com/micilini)

</div>