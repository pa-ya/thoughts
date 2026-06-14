<?php

declare(strict_types=1);

require_once __DIR__ . '/../thoughts-api/src/auth.php';
require_once __DIR__ . '/../thoughts-api/src/settings.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('dashboard.php');
}

require_admin_user();

function redirect_with_settings_error(string $message): never
{
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => $message,
    ];
    redirect_to('dashboard.php?settings_modal=1');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    redirect_with_settings_error('The form expired. Try again.');
}

$validation = validate_visual_settings_input([
    'theme_mode' => (string) ($_POST['theme_mode'] ?? ''),
    'accent_color' => (string) ($_POST['accent_color'] ?? ''),
    'base_font_size' => (string) ($_POST['base_font_size'] ?? ''),
    'font_family' => (string) ($_POST['font_family'] ?? ''),
    'density' => (string) ($_POST['density'] ?? ''),
]);

if ($validation['errors'] !== []) {
    $firstError = 'Settings could not be saved.';

    foreach ($validation['errors'] as $error) {
        if (is_string($error)) {
            $firstError = $error;
            break;
        }
    }

    redirect_with_settings_error($firstError);
}

try {
    save_visual_settings($validation['data']);
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Settings saved.',
    ];
} catch (Throwable $exception) {
    error_log('Settings update failed: ' . $exception->getMessage());
    redirect_with_settings_error('Settings could not be saved. Check the database connection and schema.');
}

redirect_to('dashboard.php');
