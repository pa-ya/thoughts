<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

start_app_session();

$appName = (string) config_get('app.name', 'Thoughts Timeline');
$errorMessage = null;
$isAuthConfigured = auth_is_configured();

if (current_role() !== null) {
    redirect_to('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');

    if ($password === '') {
        $errorMessage = 'Enter the access password.';
    } elseif (!$isAuthConfigured) {
        $errorMessage = 'Password access is not configured yet.';
    } else {
        $role = role_from_password($password);

        if ($role === null) {
            $errorMessage = 'The password is not valid.';
        } else {
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
