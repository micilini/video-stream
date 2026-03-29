# Upgrade Guide

## Migrating from v2.x to v3.0

v3.0 removes the legacy `streamVideo()` API that was deprecated since v2.0.

### What changed

The `streamVideo()` method and `StreamConfig::fromLegacyOptions()` have been removed.

### How to migrate

**Before (v1.x / v2.x legacy):**
```php
$stream = new VideoStream();
$stream->streamVideo('/path/to/video.mp4', [
    'is_localPath' => true,
    'content_type' => 'video/mp4',
]);
```

**After (v3.0+):**
```php
$stream = new VideoStream();
$stream->local('/path/to/video.mp4')
       ->contentType('video/mp4')
       ->output();
```

**Remote video — before:**
```php
$stream->streamVideo('https://example.com/video.mp4', [
    'is_localPath' => false,
]);
```

**Remote video — after:**
```php
$stream->remote('https://example.com/video.mp4')
       ->output();
```

### Removed

| Removed | Replacement |
|---------|-------------|
| `VideoStream::streamVideo(string $path, array $options)` | `->local()` / `->remote()` + `->output()` |
| `StreamConfig::fromLegacyOptions(array $options)` | `new StreamConfig(...)` directly |
| `$options['is_localPath']` | use `->local()` or `->remote()` |
| `$options['is_https']` | no longer needed |
| `$options['video_buffer']` | `->buffer(int $size)` |
| `$options['content_type']` | `->contentType(string $type)` |
| `$options['cache_control']` | `->cacheControl(string $value)` |