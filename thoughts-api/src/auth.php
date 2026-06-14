<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const ROLE_ADMIN = 'admin';
const ROLE_VIEWER = 'viewer';
const LOGIN_THROTTLE_MAX_ATTEMPTS = 5;
const LOGIN_THROTTLE_WINDOW_SECONDS = 300;
const LOGIN_THROTTLE_LOCKOUT_SECONDS = 60;

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $secureCookies = (bool) config_get('app.secure_cookies', false) || $isHttps;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_name('thoughts_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookies,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_role(): ?string
{
    start_app_session();

    $role = $_SESSION['role'] ?? null;

    if ($role !== ROLE_ADMIN && $role !== ROLE_VIEWER) {
        return null;
    }

    return $role;
}

function is_admin(): bool
{
    return current_role() === ROLE_ADMIN;
}

function is_viewer(): bool
{
    return current_role() === ROLE_VIEWER;
}

function auth_is_configured(): bool
{
    return (string) config_get('auth.admin_password_hash', '') !== ''
        && (string) config_get('auth.viewer_password_hash', '') !== '';
}

function redirect_to(string $path): never
{
    header('Location: ' . $path, true, 302);
    exit;
}

function require_authenticated_user(): string
{
    $role = current_role();

    if ($role === null) {
        redirect_to('index.php');
    }

    return $role;
}

function require_admin_user(): void
{
    $role = require_authenticated_user();

    if ($role !== ROLE_ADMIN) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function csrf_token(): string
{
    start_app_session();

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    start_app_session();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function login_throttle_limits(): array
{
    return [
        'max_attempts' => LOGIN_THROTTLE_MAX_ATTEMPTS,
        'window_seconds' => LOGIN_THROTTLE_WINDOW_SECONDS,
        'lockout_seconds' => LOGIN_THROTTLE_LOCKOUT_SECONDS,
    ];
}

function login_throttle_state(?int $now = null): array
{
    start_app_session();

    $currentTime = $now ?? time();
    $state = $_SESSION['login_throttle'] ?? [];

    if (!is_array($state)) {
        $state = [];
    }

    $failedCount = filter_var($state['failed_count'] ?? 0, FILTER_VALIDATE_INT);
    $firstFailedAt = filter_var($state['first_failed_at'] ?? 0, FILTER_VALIDATE_INT);
    $lockedUntil = filter_var($state['locked_until'] ?? 0, FILTER_VALIDATE_INT);

    $failedCount = $failedCount === false || $failedCount < 0 ? 0 : $failedCount;
    $firstFailedAt = $firstFailedAt === false || $firstFailedAt < 0 ? 0 : $firstFailedAt;
    $lockedUntil = $lockedUntil === false || $lockedUntil < 0 ? 0 : $lockedUntil;

    if (
        $failedCount === 0
        || $firstFailedAt === 0
        || (
            $currentTime - $firstFailedAt >= LOGIN_THROTTLE_WINDOW_SECONDS
            && $lockedUntil <= $currentTime
        )
    ) {
        unset($_SESSION['login_throttle']);

        return [
            'failed_count' => 0,
            'first_failed_at' => 0,
            'locked_until' => 0,
        ];
    }

    $normalized = [
        'failed_count' => $failedCount,
        'first_failed_at' => $firstFailedAt,
        'locked_until' => $lockedUntil,
    ];

    $_SESSION['login_throttle'] = $normalized;

    return $normalized;
}

function login_throttle_is_limited(?int $now = null): bool
{
    $currentTime = $now ?? time();
    $state = login_throttle_state($currentTime);

    return $state['locked_until'] > $currentTime;
}

function login_throttle_remaining_seconds(?int $now = null): int
{
    $currentTime = $now ?? time();
    $state = login_throttle_state($currentTime);

    return max(0, $state['locked_until'] - $currentTime);
}

function login_throttle_register_failure(?int $now = null): void
{
    start_app_session();

    $currentTime = $now ?? time();
    $state = login_throttle_state($currentTime);

    if ($state['locked_until'] > $currentTime) {
        return;
    }

    if ($state['failed_count'] === 0 || $state['first_failed_at'] === 0) {
        $state['first_failed_at'] = $currentTime;
    }

    $state['failed_count']++;

    if ($state['failed_count'] >= LOGIN_THROTTLE_MAX_ATTEMPTS) {
        $state['locked_until'] = $currentTime + LOGIN_THROTTLE_LOCKOUT_SECONDS;
    }

    $_SESSION['login_throttle'] = $state;
}

function login_throttle_clear(): void
{
    start_app_session();
    unset($_SESSION['login_throttle']);
}

function login_throttle_error_message(?int $now = null): string
{
    $remainingSeconds = max(1, login_throttle_remaining_seconds($now));

    return 'Too many failed attempts. Try again in ' . $remainingSeconds . ' seconds.';
}

function sign_in_as(string $role): void
{
    if ($role !== ROLE_ADMIN && $role !== ROLE_VIEWER) {
        throw new InvalidArgumentException('Unknown role.');
    }

    start_app_session();
    session_regenerate_id(true);
    $_SESSION['role'] = $role;
}

function sign_out(): void
{
    start_app_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'] ?? '',
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function role_from_password(string $password): ?string
{
    $adminHash = (string) config_get('auth.admin_password_hash', '');
    $viewerHash = (string) config_get('auth.viewer_password_hash', '');

    if ($adminHash !== '' && password_verify($password, $adminHash)) {
        return ROLE_ADMIN;
    }

    if ($viewerHash !== '' && password_verify($password, $viewerHash)) {
        return ROLE_VIEWER;
    }

    return null;
}
