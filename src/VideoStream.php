<?php

declare(strict_types=1);

namespace Micilini\VideoStream;

use Micilini\VideoStream\Auth\TokenGenerator;
use Micilini\VideoStream\Auth\TokenValidator;
use Micilini\VideoStream\Config\StreamConfig;
use Micilini\VideoStream\Cache\FileCacheHandler;
use Micilini\VideoStream\Contracts\CacheHandlerInterface;
use Micilini\VideoStream\Contracts\EventDispatcherInterface;
use Micilini\VideoStream\Contracts\StreamDriverInterface;
use Micilini\VideoStream\Drivers\LocalStreamDriver;
use Micilini\VideoStream\Drivers\RemoteStreamDriver;
use Micilini\VideoStream\Events\AfterStream;
use Micilini\VideoStream\Events\BeforeStream;
use Micilini\VideoStream\Events\CallbackEventDispatcher;
use Micilini\VideoStream\Events\StreamError;
use Micilini\VideoStream\Exceptions\InvalidConfigException;
use Micilini\VideoStream\Exceptions\StreamException;
use Micilini\VideoStream\Http\RangeRequestHandler;
use Micilini\VideoStream\Http\RateLimiter;
use Micilini\VideoStream\Subtitles\SubtitleHandler;

class VideoStream
{
    private ?StreamDriverInterface $driver = null;
    private ?StreamConfig $config = null;
    private ?CacheHandlerInterface $cacheHandler = null;
    private ?EventDispatcherInterface $eventDispatcher = null;
    private bool $isRemote = false;
    private ?string $sourcePath = null;

    private bool $cacheEnabled = false;
    private ?int $cacheTtl = null;
    private bool $cacheFresh = false;
    private ?string $cachePath = null;
    private ?int $cacheMaxDisk = null;

    private ?string $token = null;
    private ?string $tokenSecret = null;
    private ?string $tokenVideoId = null;
    private ?string $tokenIp = null;

    private ?int $rateLimitMaxConcurrent = null;
    private ?int $rateLimitMaxPerMinute = null;
    private ?string $rateLimitStoragePath = null;
    private ?string $rateLimitIp = null;
    private ?RateLimiter $rateLimiter = null;

    private ?string $subtitlePath = null;

    private array $subtitleTracks = [];

    private int $bufferSize;
    private string $contentType;
    private string $cacheControl;
    private ?string $expires = null;
    private ?string $lastModified = null;

    public function __construct(
        int $defaultBufferSize = 262144,
        string $defaultContentType = 'video/mp4',
        ?CacheHandlerInterface $cacheHandler = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->bufferSize = $defaultBufferSize;
        $this->contentType = $defaultContentType;
        $this->cacheHandler = $cacheHandler;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function local(string $path): self
    {
        $this->sourcePath = $path;
        $this->isRemote = false;
        $this->driver = new LocalStreamDriver($path);
        return $this;
    }

    public function remote(string $url): self
    {
        $this->sourcePath = $url;
        $this->isRemote = true;
        $this->driver = new RemoteStreamDriver($url);
        return $this;
    }

    public function contentType(string $type): self
    {
        $this->contentType = $type;
        return $this;
    }

    public function buffer(int $size): self
    {
        $this->bufferSize = $size;
        return $this;
    }

    public function cacheControl(string $value): self
    {
        $this->cacheControl = $value;
        return $this;
    }

    public function cache(
        bool $enabled = true,
        ?int $ttl = null,
        bool $fresh = false,
        ?string $path = null,
        ?int $maxDisk = null,
    ): self {
        $this->cacheEnabled = $enabled;
        $this->cacheTtl = $ttl;
        $this->cacheFresh = $fresh;
        $this->cachePath = $path;
        $this->cacheMaxDisk = $maxDisk;
        return $this;
    }

    public function on(string $eventName, callable $listener): self
    {
        if ($this->eventDispatcher === null) {
            $this->eventDispatcher = new CallbackEventDispatcher();
        }

        if ($this->eventDispatcher instanceof CallbackEventDispatcher) {
            $this->eventDispatcher->on($eventName, $listener);
        }

        return $this;
    }

    public static function generateToken(
        string $videoId,
        string $secret,
        int $expiresIn = 3600,
        ?string $ip = null,
    ): string {
        return TokenGenerator::generate(
            videoId: $videoId,
            secret: $secret,
            expiresIn: $expiresIn,
            ip: $ip,
        );
    }

    public function rateLimit(
        int $maxConcurrent = 3,
        int $maxPerMinute = 30,
        ?string $storagePath = null,
        ?string $ip = null,
    ): self {
        $this->rateLimitMaxConcurrent = $maxConcurrent;
        $this->rateLimitMaxPerMinute = $maxPerMinute;
        $this->rateLimitStoragePath = $storagePath;
        $this->rateLimitIp = $ip;

        return $this;
    }

    public function subtitle(string $path): self
    {
        $this->subtitlePath = $path;

        return $this;
    }

    public function subtitles(array $tracks): self
    {
        $this->subtitleTracks = $tracks;

        return $this;
    }

    public function getSubtitles(): array
    {
        return $this->subtitleTracks;
    }

    public function withToken(
        string $token,
        string $secret,
        string $videoId,
        ?string $ip = null,
    ): self {
        $this->token = $token;
        $this->tokenSecret = $secret;
        $this->tokenVideoId = $videoId;
        $this->tokenIp = $ip;

        return $this;
    }

    public function output(): void
    {
        $startedAt = microtime(true);

        try {
            if ($this->subtitlePath !== null) {
                SubtitleHandler::output($this->subtitlePath);
                return;
            }

            $this->ensureDriverConfigured();
            $this->ensureTokenAuthorized();
            $this->acquireRateLimit();
            $this->resolveCache();
            $this->buildConfig();

            $this->dispatchBeforeStream();

            $size = $this->driver->getSize();

            if ($size === null || $size <= 0) {
                $this->directPassthrough();
                $this->dispatchAfterStream(
                    bytesSent: null,
                    startedAt: $startedAt,
                );
                return;
            }

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $rangeHandler = new RangeRequestHandler($size, $this->config);
            $rangeHandler->sendHeaders();

            if ($this->subtitleTracks !== []) {
                header(
                    'X-Subtitles: ' . json_encode($this->subtitleTracks, JSON_UNESCAPED_SLASHES)
                );
            }

            $start = $rangeHandler->getStart();
            $end = $rangeHandler->getEnd();

            try {
                $this->driver->stream(
                    $start,
                    $end,
                    $this->config->bufferSize,
                );
            } finally {
                $this->driver->close();
            }

            $this->dispatchAfterStream(
                bytesSent: ($end - $start) + 1,
                startedAt: $startedAt,
            );
        } catch (\Throwable $e) {
            $this->dispatchError($e);
            throw $e;
        } finally {
            $this->releaseRateLimit();
        }
    }

    public function stream(): mixed
    {
        if (class_exists(\Symfony\Component\HttpFoundation\StreamedResponse::class)) {
            return $this->toStreamedResponse();
        }

        $this->output();
        return null;
    }

    private function ensureDriverConfigured(): void
    {
        if ($this->driver === null) {
            throw new InvalidConfigException(
                'No video source configured. Call ->local() or ->remote() first.'
            );
        }
    }

    private function buildConfig(): void
    {
        if ($this->config !== null) {
            return;
        }

        $this->config = new StreamConfig(
            bufferSize: $this->bufferSize,
            contentType: $this->contentType,
            cacheControl: $this->cacheControl ?? 'max-age=2592000, public',
            expires: $this->expires,
            lastModified: $this->lastModified,
        );
    }

    private function resolveCache(): void
    {
        if (!$this->isRemote || !$this->cacheEnabled) {
            return;
        }

        $handler = $this->cacheHandler;

        if ($handler === null && $this->cachePath !== null) {
            $handler = new FileCacheHandler(
                cachePath: $this->cachePath,
                defaultTtl: $this->cacheTtl ?? 86400,
                maxDiskUsage: $this->cacheMaxDisk ?? 10_737_418_240,
                eventDispatcher: $this->eventDispatcher,
            );
        }

        if ($handler === null) {
            return;
        }

        $localPath = $handler->resolve($this->sourcePath, $this->cacheTtl, $this->cacheFresh);
        $this->driver = new LocalStreamDriver($localPath);
        $this->isRemote = false;
    }

    private function directPassthrough(): void
    {
        if (!$this->isRemote || !($this->driver instanceof RemoteStreamDriver)) {
            throw new StreamException('Cannot determine video size for local file: ' . $this->sourcePath);
        }

        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Content-Type: ' . $this->contentType);
        header('Cache-Control: ' . ($this->cacheControl ?? 'max-age=2592000, public'));
        set_time_limit(0);

        $this->driver->stream(0, PHP_INT_MAX, $this->bufferSize);
        $this->driver->close();
    }

    private function toStreamedResponse(): object
    {
        if ($this->subtitlePath !== null) {
            return new \Symfony\Component\HttpFoundation\Response(
                file_get_contents($this->subtitlePath) ?: '',
                200,
                ['Content-Type' => 'text/vtt; charset=utf-8']
            );
        }

        $this->ensureDriverConfigured();
        $this->ensureTokenAuthorized();
        $this->acquireRateLimit();
        $this->resolveCache();
        $this->buildConfig();

        $size = $this->driver->getSize();

        if ($size === null || $size <= 0) {
            $driver = $this->driver;
            $bufferSize = $this->config->bufferSize;
            $contentType = $this->config->contentType;

            return new \Symfony\Component\HttpFoundation\StreamedResponse(
                function () use ($driver, $bufferSize) {
                    $startedAt = microtime(true);

                    try {
                        $this->dispatchBeforeStream();

                        set_time_limit(0);

                    try {
                        $driver->stream(0, PHP_INT_MAX, $bufferSize);
                    } finally {
                        $driver->close();
                    }

                    $this->dispatchAfterStream(
                        bytesSent: null,
                        startedAt: $startedAt,
                    );
                    } catch (\Throwable $e) {
                        $this->dispatchError($e);
                        throw $e;
                    } finally {
                        $this->releaseRateLimit();
                    }
                },
                200,
                ['Content-Type' => $contentType]
            );
        }

        $rangeHandler = new RangeRequestHandler($size, $this->config);
        $driver = $this->driver;
        $bufferSize = $this->config->bufferSize;
        $start = $rangeHandler->getStart();
        $end = $rangeHandler->getEnd();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($driver, $start, $end, $bufferSize) {
                $startedAt = microtime(true);

                try {
                    $this->dispatchBeforeStream();

                    try {
                        $driver->stream($start, $end, $bufferSize);
                    } finally {
                        $driver->close();
                    }

                    $this->dispatchAfterStream(
                        bytesSent: ($end - $start) + 1,
                        startedAt: $startedAt,
                    );
                } catch (\Throwable $e) {
                    $this->dispatchError($e);
                    throw $e;
                } finally {
                    $this->releaseRateLimit();
                }
            },
            $rangeHandler->getStatusCode(),
            array_merge(
                $rangeHandler->getHeaders(),
                $this->subtitleTracks !== []
                    ? ['X-Subtitles' => json_encode($this->subtitleTracks, JSON_UNESCAPED_SLASHES)]
                    : []
            )
        );
    }

    private function acquireRateLimit(): void
    {
        if ($this->rateLimitStoragePath === null || $this->rateLimitStoragePath === '') {
            return;
        }

        if ($this->rateLimiter !== null) {
            return;
        }

        $this->rateLimiter = new RateLimiter(
            storagePath: $this->rateLimitStoragePath,
            maxConcurrent: $this->rateLimitMaxConcurrent ?? 3,
            maxPerMinute: $this->rateLimitMaxPerMinute ?? 30,
            ipAddress: $this->rateLimitIp,
        );

        $this->rateLimiter->acquire();
    }

    private function releaseRateLimit(): void
    {
        if ($this->rateLimiter === null) {
            return;
        }

        $this->rateLimiter->release();
        $this->rateLimiter = null;
    }

    private function dispatchBeforeStream(): void
    {
        $this->eventDispatcher?->dispatch(new BeforeStream([
            'source' => $this->sourcePath,
            'isRemote' => $this->isRemote,
            'contentType' => $this->contentType,
            'bufferSize' => $this->bufferSize,
            'cacheEnabled' => $this->cacheEnabled,
            'tokenProtected' => $this->token !== null,
            'tokenVideoId' => $this->tokenVideoId,
            'rateLimited' => $this->rateLimitStoragePath !== null,
            'rateLimitMaxConcurrent' => $this->rateLimitMaxConcurrent,
            'rateLimitMaxPerMinute' => $this->rateLimitMaxPerMinute,
        ]));
    }

    private function dispatchAfterStream(?int $bytesSent, float $startedAt): void
    {
        $this->eventDispatcher?->dispatch(new AfterStream([
            'source' => $this->sourcePath,
            'isRemote' => $this->isRemote,
            'bytesSent' => $bytesSent,
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
            'contentType' => $this->contentType,
        ]));
    }

    private function dispatchError(\Throwable $e): void
    {
        $this->eventDispatcher?->dispatch(new StreamError([
            'source' => $this->sourcePath,
            'isRemote' => $this->isRemote,
            'message' => $e->getMessage(),
            'exception' => $e,
        ]));
    }

    private function ensureTokenAuthorized(): void
    {
        if ($this->token === null) {
            return;
        }

        if ($this->tokenSecret === null || $this->tokenVideoId === null) {
            throw new InvalidConfigException(
                'Token validation requires token, secret and videoId.'
            );
        }

        TokenValidator::validate(
            token: $this->token,
            secret: $this->tokenSecret,
            videoId: $this->tokenVideoId,
            ip: $this->tokenIp,
        );
    }
}
