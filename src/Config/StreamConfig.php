<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Config;

use Micilini\VideoStream\Exceptions\InvalidConfigException;

final readonly class StreamConfig
{
    public int $bufferSize;
    public string $contentType;
    public string $cacheControl;
    public string $expires;
    public string $lastModified;

    public function __construct(
        int $bufferSize = 262144,
        string $contentType = 'video/mp4',
        string $cacheControl = 'max-age=2592000, public',
        ?string $expires = null,
        ?string $lastModified = null,
    ) {
        if ($bufferSize < 1024) {
            throw new InvalidConfigException(
                "Buffer size must be at least 1024 bytes, got {$bufferSize}"
            );
        }

        $this->bufferSize = $bufferSize;
        $this->contentType = $contentType;
        $this->cacheControl = $cacheControl;
        $this->expires = $expires ?? gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT';
        $this->lastModified = $lastModified ?? gmdate('D, d M Y H:i:s') . ' GMT';
    }

    public static function fromLegacyOptions(array $options, ?string $filePath = null): self
    {
        return new self(
            bufferSize: isset($options['video_buffer']) ? (int) $options['video_buffer'] : 262144,
            contentType: $options['content_type'] ?? 'video/mp4',
            cacheControl: $options['cache_control'] ?? 'max-age=2592000, public',
            expires: $options['expires'] ?? null,
            lastModified: $options['last_modified']
                ?? ($filePath && @filemtime($filePath)
                    ? gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT'
                    : null),
        );
    }
}
