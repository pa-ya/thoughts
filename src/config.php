<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config = [
        'app' => [
            'name' => getenv('APP_NAME') ?: 'Thoughts Timeline',
            'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
            'secure_cookies' => filter_var(getenv('APP_SECURE_COOKIES') ?: false, FILTER_VALIDATE_BOOL),
        ],
        'database' => [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => getenv('DB_NAME') ?: 'thoughts',
            'user' => getenv('DB_USER') ?: 'thoughts_user',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        ],
        'auth' => [
            'admin_password_hash' => getenv('ADMIN_PASSWORD_HASH') ?: '',
            'viewer_password_hash' => getenv('VIEWER_PASSWORD_HASH') ?: '',
        ],
    ];

    $localConfigPath = dirname(__DIR__) . '/config.local.php';

    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;

        if (!is_array($localConfig)) {
            throw new RuntimeException('Local config file must return an array.');
        }

        $config = array_replace_recursive($config, $localConfig);
    }

    date_default_timezone_set((string) $config['app']['timezone']);

    return $config;
}

function config_get(string $path, $default = null)
{
    $value = app_config();

    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

