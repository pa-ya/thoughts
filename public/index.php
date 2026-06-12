<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

start_app_session();

$appName = (string) config_get('app.name', 'Thoughts Timeline');
$currentRole = current_role();
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
            <p class="eyebrow">Phase 1</p>
            <h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="lede">
                The PHP project foundation is ready. Login, database schema, and dashboard behavior will be implemented in the next phases.
            </p>

            <div class="status-grid">
                <div>
                    <span class="label">Session role</span>
                    <strong><?= htmlspecialchars($currentRole ?? 'Guest', ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div>
                    <span class="label">Config source</span>
                    <strong><?= is_file(__DIR__ . '/../config.local.php') ? 'Local file' : 'Defaults/env' ?></strong>
                </div>
            </div>

            <a class="button" href="dashboard.php">Open dashboard shell</a>
        </section>
    </main>
</body>
</html>

