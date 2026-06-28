<?php

return [
    'disk' => env('TARDIS_MEDIA_DISK', 'public'),
    'path' => env('TARDIS_MEDIA_PATH', 'media'),
    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'],
    'max_file_size' => env('TARDIS_MEDIA_MAX_SIZE', 10240), // KB
    'thumbnail_size' => [
        'width' => 300,
        'height' => 300,
    ],
];
