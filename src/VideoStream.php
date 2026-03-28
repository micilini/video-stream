<?php

declare(strict_types=1);

namespace Micilini\VideoStream;

use Micilini\VideoStream\Config\StreamConfig;
use Micilini\VideoStream\Cache\FileCacheHandler;
use Micilini\VideoStream\Contracts\CacheHandlerInterface;
use Micilini\VideoStream\Contracts\StreamDriverInterface;
use Micilini\VideoStream\Drivers\LocalStreamDriver;
use Micilini\VideoStream\Drivers\RemoteStreamDriver;
use Micilini\VideoStream\Exceptions\InvalidConfigException;
use Micilini\VideoStream\Exceptions\StreamException;
use Micilini\VideoStream\Http\RangeRequestHandler;

class VideoStream
{
    private ?StreamDriverInterface $driver = null;
    private ?StreamConfig $config = null;
    private ?CacheHandlerInterface $cacheHandler = null;
    private bool $isRemote = false;
    private ?string $sourcePath = null;

    private bool $cacheEnabled = false;
    private ?int $cacheTtl = null;
    private bool $cacheFresh = false;
    private ?string $cachePath = null;
    private ?int $cacheMaxDisk = null;

    private int $bufferSize;
    private string $contentType;
    private string $cacheControl;
    private ?string $expires = null;
    private ?string $lastModified = null;

    public function __construct(
        int $defaultBufferSize = 262144,
        string $defaultContentType = 'video/mp4',
        ?CacheHandlerInterface $cacheHandler = null,
    ) {
        $this->bufferSize = $defaultBufferSize;
        $this->contentType = $defaultContentType;
        $this->cacheHandler = $cacheHandler;
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

    public function output(): void
    {
        $this->ensureDriverConfigured();
        $this->resolveCache();
        $this->buildConfig();

        $size = $this->driver->getSize();

        if ($size === null || $size <= 0) {
            $this->directPassthrough();
            return;
        }

        ob_get_clean();

        $rangeHandler = new RangeRequestHandler($size, $this->config);
        $rangeHandler->sendHeaders();

        $this->driver->stream(
            $rangeHandler->getStart(),
            $rangeHandler->getEnd(),
            $this->config->bufferSize,
        );

        $this->driver->close();
    }

    public function stream(): mixed
    {
        if (class_exists(\Symfony\Component\HttpFoundation\StreamedResponse::class)) {
            return $this->toStreamedResponse();
        }

        $this->output();
        return null;
    }

    /**
     * @deprecated Use ->local() / ->remote() + ->output() instead. Will be removed in v3.0.
     */
    public function streamVideo(string $videoPath, array $options = []): void
    {
        @trigger_error(
            'VideoStream::streamVideo() is deprecated since v2.0. Use ->local() / ->remote() + ->output() instead.',
            E_USER_DEPRECATED
        );

        $isLocal = $options['is_localPath'] ?? null;

        if ($isLocal === null) {
            throw new InvalidConfigException(
                'You must define [is_localPath] in $options.'
            );
        }

        $this->config = StreamConfig::fromLegacyOptions($options, $isLocal ? $videoPath : null);
        $this->bufferSize = $this->config->bufferSize;
        $this->contentType = $this->config->contentType;

        if ($isLocal) {
            $this->local($videoPath);
        } else {
            $this->remote($videoPath);
        }

        $this->output();
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

        ob_get_clean();
        header('Content-Type: ' . $this->contentType);
        header('Cache-Control: ' . ($this->cacheControl ?? 'max-age=2592000, public'));
        set_time_limit(0);

        $this->driver->stream(0, PHP_INT_MAX, $this->bufferSize);
        $this->driver->close();
    }

    private function toStreamedResponse(): object
    {
        $this->ensureDriverConfigured();
        $this->resolveCache();
        $this->buildConfig();

        $size = $this->driver->getSize();

        if ($size === null || $size <= 0) {

            $driver = $this->driver;
            $bufferSize = $this->config->bufferSize;
            $contentType = $this->config->contentType;

            return new \Symfony\Component\HttpFoundation\StreamedResponse(
                function () use ($driver, $bufferSize) {
                    set_time_limit(0);
                    $driver->stream(0, PHP_INT_MAX, $bufferSize);
                    $driver->close();
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
                $driver->stream($start, $end, $bufferSize);
                $driver->close();
            },
            $rangeHandler->getStatusCode(),
            $rangeHandler->getHeaders()
        );
    }
}
