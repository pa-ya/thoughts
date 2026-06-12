<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/database.php';

start_app_session();

$appName = (string) config_get('app.name', 'Thoughts Timeline');
$currentRole = current_role();
$databaseStatus = database_health_check();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
    <main class="dashboard-shell">
        <header class="dashboard-header">
            <div>
                <p class="eyebrow">Dashboard Shell</p>
                <h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <span class="role-pill"><?= htmlspecialchars($currentRole ?? 'Guest', ENT_QUOTES, 'UTF-8') ?></span>
        </header>

        <section class="panel">
            <h2>Foundation Status</h2>
            <div class="status-list">
                <div class="status-row">
                    <span>Configuration loader</span>
                    <strong>Ready</strong>
                </div>
                <div class="status-row">
                    <span>Session helper</span>
                    <strong>Ready</strong>
                </div>
                <div class="status-row">
                    <span>Database helper</span>
                    <strong class="<?= $databaseStatus['ok'] ? 'status-ok' : 'status-error' ?>">
                        <?= $databaseStatus['ok'] ? 'Connected' : 'Needs config' ?>
                    </strong>
                </div>
            </div>

            <p class="notice">
                <?= htmlspecialchars($databaseStatus['message'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </section>
    </main>
</body>
</html>

