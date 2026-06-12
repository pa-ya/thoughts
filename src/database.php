<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $database = app_config()['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $database['host'],
        $database['port'],
        $database['name'],
        $database['charset']
    );

    $pdo = new PDO($dsn, $database['user'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function database_health_check(): array
{
    try {
        db()->query('SELECT 1');

        return [
            'ok' => true,
            'message' => 'Database connection is ready.',
        ];
    } catch (Throwable $exception) {
        error_log('Database connection failed: ' . $exception->getMessage());

        return [
            'ok' => false,
            'message' => 'Database connection is not configured or not reachable.',
        ];
    }
}
