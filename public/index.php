<?php

declare(strict_types=1);

require_once __DIR__ . '/../thoughts-api/src/auth.php';

start_app_session();

$appName = (string) config_get('app.name', 'Thoughts Timeline');
$errorMessage = null;
$isAuthConfigured = auth_is_configured();

if (current_role() !== null) {
    redirect_to('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'The form expired. Try again.';
    } elseif (login_throttle_is_limited()) {
        $errorMessage = login_throttle_error_message();
    } elseif ($password === '') {
        $errorMessage = 'Enter the access password.';
    } elseif (!$isAuthConfigured) {
        $errorMessage = 'Password access is not configured yet.';
    } else {
        $role = role_from_password($password);

        if ($role === null) {
            login_throttle_register_failure();
            $errorMessage = login_throttle_is_limited()
                ? login_throttle_error_message()
                : 'The password is not valid.';
        } else {
            login_throttle_clear();
            sign_in_as($role);
            redirect_to('dashboard.php');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
    <main class="auth-shell">
        <section class="panel panel-narrow">
            <p class="eyebrow">Private Timeline</p>
            <h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="lede">Enter the admin or viewer password to continue.</p>

            <?php if ($errorMessage !== null): ?>
                <p class="alert alert-error" role="alert">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <?php if (!$isAuthConfigured): ?>
                <p class="alert alert-warning">
                    Set admin and viewer password hashes in <code>config.local.php</code> or environment variables before logging in.
                </p>
            <?php endif; ?>

            <form class="login-form" method="post" action="index.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <label for="password">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    autofocus
                >
                <button class="button button-full" type="submit">Enter Dashboard</button>
            </form>
        </section>
    </main>
</body>
</html>
