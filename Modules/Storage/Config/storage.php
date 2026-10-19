<?php

return [
    'default' => getenv('STORAGE_DRIVER') ?: 'local',

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => dirname(__DIR__, 3) . '/Public/uploads',
            'url'    => '/taskmanager/Public/uploads',
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => getenv('AWS_ACCESS_KEY_ID') ?: '',
            'secret'                  => getenv('AWS_SECRET_ACCESS_KEY') ?: '',
            'region'                  => getenv('AWS_DEFAULT_REGION') ?: 'us-east-1',
            'bucket'                  => getenv('AWS_BUCKET') ?: '',
            'endpoint'                => getenv('AWS_ENDPOINT') ?: '',
            'use_path_style_endpoint' => true,
            'url'                     => getenv('AWS_URL') ?: '',
        ],
    ],

    'max_size_bytes' => 20 * 1024 * 1024,

    'allowed_mimes' => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp',
        'application/pdf',
        'text/plain', 'text/markdown', 'text/csv',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip', 'application/x-zip-compressed', 'multipart/x-zip',
        'application/x-rar-compressed', 'application/vnd.rar',
        'application/x-7z-compressed',
        'application/octet-stream'
    ],

    'allowed_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
        'pdf', 'txt', 'md', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'rar', '7z', 'tar', 'gz'
    ],

    'disallowed_extensions' => [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'dll', 'so', 'bin', 'sh', 'bash', 'bat', 'cmd', 'ps1', 'vbs',
        'js', 'jsp', 'asp', 'aspx', 'py', 'rb', 'pl', 'cgi',
        'htaccess', 'htpasswd', 'env', 'config'
    ]
];
