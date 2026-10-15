<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Driver
    |--------------------------------------------------------------------------
    | Supported drivers: 'local', 's3' (AWS S3 & Cloudflare R2 compatible)
    | Sourced from environment variable (STORAGE_DRIVER) or defaults to 'local'.
    */
    'default' => getenv('STORAGE_DRIVER') ?: 'local',

    /*
    |--------------------------------------------------------------------------
    | Storage Disks
    |--------------------------------------------------------------------------
    */
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => __DIR__ . '/../Public/uploads',
            'url'    => '/taskmanager/Public/uploads',
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => getenv('AWS_ACCESS_KEY_ID') ?: '',
            'secret'                  => getenv('AWS_SECRET_ACCESS_KEY') ?: '',
            'region'                  => getenv('AWS_DEFAULT_REGION') ?: 'us-east-1',
            'bucket'                  => getenv('AWS_BUCKET') ?: '',
            'endpoint'                => getenv('AWS_ENDPOINT') ?: '', // Cloudflare R2 / MinIO endpoint e.g. https://<account_id>.r2.cloudflarestorage.com
            'use_path_style_endpoint' => true,
            'url'                     => getenv('AWS_URL') ?: '',     // CDN or Public Bucket URL (e.g. https://pub-xxxx.r2.dev)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & Validation Layer
    |--------------------------------------------------------------------------
    */
    'max_size_bytes' => 20 * 1024 * 1024, // 20 MB Maximum file size

    'allowed_mimes' => [
        // Images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp',
        // Documents & Office
        'application/pdf',
        'text/plain', 'text/markdown', 'text/csv',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Archives
        'application/zip', 'application/x-zip-compressed', 'multipart/x-zip',
        'application/x-rar-compressed', 'application/vnd.rar',
        'application/x-7z-compressed',
        'application/octet-stream' // generic octet-stream sent by some browsers for archives
    ],

    'allowed_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
        'pdf', 'txt', 'md', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'rar', '7z', 'tar', 'gz'
    ],

    'disallowed_extensions' => [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'sh', 'bat', 'cmd', 'bin', 'js', 'vbs', 'scr', 'cgi', 'pl', 'msi', 'com'
    ]
];
