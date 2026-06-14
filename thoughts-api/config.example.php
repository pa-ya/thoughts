<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Thoughts Timeline',
        'timezone' => 'UTC',
        'secure_cookies' => false,
    ],

    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'thoughts',
        'user' => 'thoughts_user',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'auth' => [
        // Generate hashes with:
        // php -r "echo password_hash('your-admin-password', PASSWORD_DEFAULT) . PHP_EOL;"
        'admin_password_hash' => '',

        // php -r "echo password_hash('your-viewer-password', PASSWORD_DEFAULT) . PHP_EOL;"
        'viewer_password_hash' => '',
    ],
];
