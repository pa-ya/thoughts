<?php

declare(strict_types=1);

function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function app_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    load_env_file(dirname(__DIR__) . '/.env');

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
            'collation' => getenv('DB_COLLATION') ?: 'utf8mb4_0900_ai_ci',
        ],
        'auth' => [
            'admin_password_hash' => getenv('ADMIN_PASSWORD_HASH') ?: '',
            'viewer_password_hash' => getenv('VIEWER_PASSWORD_HASH') ?: '',
        ],
    ];

    $configuredPath = getenv('APP_CONFIG_FILE');
    $localConfigPath = $configuredPath !== false && trim($configuredPath) !== ''
        ? $configuredPath
        : dirname(__DIR__) . '/config.local.php';

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
