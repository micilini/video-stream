<?php

return [

    'buffer_size' => 262144,

    'default_content_type' => 'video/mp4',

    'cache' => [
        'enabled' => (bool) env('VIDEO_STREAM_CACHE', false),
        'path' => storage_path('app/video-cache'),
        'ttl' => (int) env('VIDEO_STREAM_CACHE_TTL', 86400),
        'max_disk_usage' => (int) env('VIDEO_STREAM_CACHE_MAX_DISK', 10 * 1024 * 1024 * 1024),
    ],

    'events' => [
        'enabled' => (bool) env('VIDEO_STREAM_EVENTS', true),
    ],

    'auth' => [
        'secret' => (string) env('VIDEO_STREAM_AUTH_SECRET', ''),
        'default_ttl' => (int) env('VIDEO_STREAM_AUTH_TTL', 3600),
    ],

    'rate_limit' => [
        'enabled' => (bool) env('VIDEO_STREAM_RATE_LIMIT', false),
        'max_concurrent' => (int) env('VIDEO_STREAM_RATE_LIMIT_MAX_CONCURRENT', 3),
        'max_per_minute' => (int) env('VIDEO_STREAM_RATE_LIMIT_MAX_PER_MINUTE', 30),
        'storage_path' => (string) env('VIDEO_STREAM_RATE_LIMIT_STORAGE', storage_path('app/video-stream-rate-limit')),
    ],

];
