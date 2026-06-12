<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const ROLE_ADMIN = 'admin';
const ROLE_VIEWER = 'viewer';

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secureCookies = (bool) config_get('app.secure_cookies', false);

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
