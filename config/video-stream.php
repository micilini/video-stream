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

];
