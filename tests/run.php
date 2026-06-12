<?php

declare(strict_types=1);

$root = dirname(__DIR__);

putenv('APP_CONFIG_FILE=/tmp/thoughts-tests-no-local-config.php');
putenv('APP_NAME=Test Thoughts Timeline');
putenv('APP_TIMEZONE=UTC');
putenv('APP_SECURE_COOKIES=false');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3307');
putenv('DB_NAME=thoughts_test');
putenv('DB_USER=test_user');
putenv('DB_PASSWORD=test_password');
putenv('DB_CHARSET=utf8mb4');
putenv('ADMIN_PASSWORD_HASH=' . password_hash('admin-secret', PASSWORD_DEFAULT));
putenv('VIEWER_PASSWORD_HASH=' . password_hash('viewer-secret', PASSWORD_DEFAULT));

require_once $root . '/src/auth.php';
require_once $root . '/src/events.php';
require_once $root . '/src/comments.php';

$tests = [];

function test(string $name, callable $callback): void
{
    global $tests;

    $tests[] = [$name, $callback];
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s Expected %s, got %s.',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assert_null($actual, string $message): void
{
    if ($actual !== null) {
        throw new RuntimeException(sprintf('%s Expected null, got %s.', $message, var_export($actual, true)));
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Missing: ' . $needle);
    }
}

test('configuration loads deterministic test values', function (): void {
    assert_same('Test Thoughts Timeline', config_get('app.name'), 'App name should come from the environment.');
    assert_same('UTC', config_get('app.timezone'), 'Timezone should come from the environment.');
    assert_same(false, config_get('app.secure_cookies'), 'Secure cookie flag should parse false.');
    assert_same(3307, config_get('database.port'), 'Database port should be cast to an integer.');
    assert_same('fallback', config_get('missing.path', 'fallback'), 'Missing config path should return the fallback.');
});

test('password configuration and role resolution work', function (): void {
    assert_true(auth_is_configured(), 'Auth should be configured when both password hashes are present.');
    assert_same(ROLE_ADMIN, role_from_password('admin-secret'), 'Admin password should resolve to admin role.');
    assert_same(ROLE_VIEWER, role_from_password('viewer-secret'), 'Viewer password should resolve to viewer role.');
    assert_null(role_from_password('wrong-secret'), 'Wrong password should not resolve to a role.');
});

test('session role helpers accept only known roles', function (): void {
    start_app_session();
    $_SESSION = [];

    assert_null(current_role(), 'Empty session should have no role.');

    $_SESSION['role'] = 'invalid';
    assert_null(current_role(), 'Unknown role should be ignored.');

    sign_in_as(ROLE_ADMIN);
    assert_same(ROLE_ADMIN, current_role(), 'Admin sign-in should set admin role.');
    assert_true(is_admin(), 'Admin helper should be true after admin sign-in.');
    assert_true(!is_viewer(), 'Viewer helper should be false after admin sign-in.');

    sign_in_as(ROLE_VIEWER);
    assert_same(ROLE_VIEWER, current_role(), 'Viewer sign-in should set viewer role.');
    assert_true(is_viewer(), 'Viewer helper should be true after viewer sign-in.');

    $thrown = false;

    try {
        sign_in_as('editor');
    } catch (InvalidArgumentException) {
        $thrown = true;
    }

    assert_true($thrown, 'Unknown sign-in role should throw.');
});

test('domain constants match field requirements', function (): void {
    assert_same(1024, event_field_max_length(), 'Event text fields should allow 1024 characters.');
    assert_same(['min' => 0.0, 'max' => 10.0], feeling_rate_bounds(), 'Feeling rate bounds should be 0 to 10.');
    assert_same(1024, comment_field_max_length(), 'Comment field should allow 1024 characters.');
});

test('schema includes required tables, constraints, and defaults', function () use ($root): void {
    $schema = file_get_contents($root . '/database/schema.sql');

    if ($schema === false) {
        throw new RuntimeException('Could not read database/schema.sql.');
    }

    assert_contains('CREATE TABLE IF NOT EXISTS events', $schema, 'Schema should create events table.');
    assert_contains('CREATE TABLE IF NOT EXISTS comments', $schema, 'Schema should create comments table.');
    assert_contains('CREATE TABLE IF NOT EXISTS settings', $schema, 'Schema should create settings table.');
    assert_contains('ON DELETE CASCADE', $schema, 'Comments should cascade when an event is deleted.');
    assert_contains('CHAR_LENGTH(event_text) <= 1024', $schema, 'Event text length should be constrained.');
    assert_contains('CHAR_LENGTH(thoughts) <= 1024', $schema, 'Thoughts length should be constrained.');
    assert_contains('CHAR_LENGTH(physical_effect) <= 1024', $schema, 'Physical effect length should be constrained.');
    assert_contains('feeling_rate >= 0 AND feeling_rate <= 10', $schema, 'Feeling rate range should be constrained.');
    assert_contains("('theme_mode', 'system')", $schema, 'Schema should seed theme mode setting.');
});

$failures = [];

foreach ($tests as [$name, $callback]) {
    try {
        $callback();
    } catch (Throwable $throwable) {
        $failures[] = [$name, $throwable->getMessage()];
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

if ($failures !== []) {
    echo "Tests failed:\n";

    foreach ($failures as [$name, $message]) {
        echo "- {$name}: {$message}\n";
    }

    exit(1);
}

echo sprintf("OK (%d tests)\n", count($tests));
