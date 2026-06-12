<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/events.php';

start_app_session();

$appName = (string) config_get('app.name', 'Thoughts Timeline');
$currentRole = require_authenticated_user();
$databaseStatus = database_health_check();
$timelineGroups = [];
$timelineError = null;

if ($databaseStatus['ok']) {
    try {
        $timelineGroups = group_events_by_month_day(fetch_timeline_events());
    } catch (Throwable $exception) {
        error_log('Timeline query failed: ' . $exception->getMessage());
        $timelineError = 'Timeline data is not available. Make sure the database schema has been imported.';
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pluralize_count(int $count, string $singular, string $plural): string
{
    return $count . ' ' . ($count === 1 ? $singular : $plural);
}

function event_preview(string $text): string
{
    $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    $length = function_exists('mb_strlen') ? mb_strlen($normalized) : strlen($normalized);

    if ($length <= 170) {
        return $normalized;
    }

    $preview = function_exists('mb_substr')
        ? mb_substr($normalized, 0, 167)
        : substr($normalized, 0, 167);

    return $preview . '...';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | <?= e($appName) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
    <main class="dashboard-shell">
        <header class="dashboard-header">
            <div>
                <p class="eyebrow">Timeline</p>
                <h1><?= e($appName) ?></h1>
            </div>
            <div class="header-actions">
                <span class="role-pill"><?= e(ucfirst($currentRole)) ?></span>
                <a class="button button-secondary" href="logout.php">Logout</a>
            </div>
        </header>

        <section class="dashboard-meta" aria-label="Dashboard status">
            <div>
                <span class="label">Database</span>
                <strong class="<?= $databaseStatus['ok'] ? 'status-ok' : 'status-error' ?>">
                    <?= $databaseStatus['ok'] ? 'Connected' : 'Needs config' ?>
                </strong>
            </div>
            <div>
                <span class="label">Access</span>
                <strong><?= e(ucfirst($currentRole)) ?></strong>
            </div>
        </section>

        <?php if (!$databaseStatus['ok']): ?>
            <section class="panel empty-state">
                <p class="eyebrow">Setup Required</p>
                <h2>Connect the database to view the timeline.</h2>
                <p class="notice"><?= e((string) $databaseStatus['message']) ?></p>
            </section>
        <?php elseif ($timelineError !== null): ?>
            <section class="panel empty-state">
                <p class="eyebrow">Timeline</p>
                <h2>Timeline cannot be loaded yet.</h2>
                <p class="notice"><?= e($timelineError) ?></p>
            </section>
        <?php elseif ($timelineGroups === []): ?>
            <section class="panel empty-state">
                <p class="eyebrow">Timeline</p>
                <h2>No events recorded yet.</h2>
                <p class="notice">Once events are added, they will appear here grouped by month and day.</p>
            </section>
        <?php else: ?>
            <section class="timeline" aria-label="Events grouped by month and day">
                <?php foreach ($timelineGroups as $monthIndex => $month): ?>
                    <?php $monthPanelId = 'month-panel-' . $monthIndex; ?>
                    <section class="accordion-item timeline-month">
                        <button
                            class="accordion-toggle month-toggle"
                            type="button"
                            aria-expanded="false"
                            aria-controls="<?= e($monthPanelId) ?>"
                            data-accordion-toggle
                        >
                            <span class="accordion-icon" aria-hidden="true">&gt;</span>
                            <span>
                                <span class="accordion-title"><?= e((string) $month['label']) ?></span>
                                <span class="accordion-subtitle"><?= e(pluralize_count((int) $month['event_count'], 'event', 'events')) ?></span>
                            </span>
                        </button>

                        <div class="accordion-panel month-panel" id="<?= e($monthPanelId) ?>" hidden>
                            <?php foreach ($month['days'] as $dayIndex => $day): ?>
                                <?php $dayPanelId = 'day-panel-' . $monthIndex . '-' . $dayIndex; ?>
                                <section class="accordion-item timeline-day">
                                    <button
                                        class="accordion-toggle day-toggle"
                                        type="button"
                                        aria-expanded="false"
                                        aria-controls="<?= e($dayPanelId) ?>"
                                        data-accordion-toggle
                                    >
                                        <span class="accordion-icon" aria-hidden="true">&gt;</span>
                                        <span>
                                            <span class="accordion-title"><?= e((string) $day['label']) ?></span>
                                            <span class="accordion-subtitle"><?= e(pluralize_count((int) $day['event_count'], 'event', 'events')) ?></span>
                                        </span>
                                    </button>

                                    <div class="accordion-panel day-panel" id="<?= e($dayPanelId) ?>" hidden>
                                        <div class="event-list">
                                            <?php foreach ($day['events'] as $event): ?>
                                                <article class="event-item">
                                                    <div class="event-main">
                                                        <h3><?= e(event_preview((string) $event['event_text'])) ?></h3>
                                                        <dl class="event-facts">
                                                            <div>
                                                                <dt>Feeling</dt>
                                                                <dd><?= e(format_feeling_rate((float) $event['feeling_rate'])) ?>/10</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Date</dt>
                                                                <dd><?= e((string) $event['event_date_label']) ?></dd>
                                                            </div>
                                                        </dl>
                                                    </div>

                                                    <div class="event-badges" aria-label="Event status">
                                                        <?php if ((int) $event['comment_count'] > 0): ?>
                                                            <span class="comment-badge">
                                                                <?= e(pluralize_count((int) $event['comment_count'], 'comment', 'comments')) ?>
                                                            </span>
                                                        <?php endif; ?>

                                                        <?php if ($currentRole === ROLE_ADMIN && (int) $event['unread_comment_count'] > 0): ?>
                                                            <span class="comment-badge comment-badge-new">
                                                                <?= e(pluralize_count((int) $event['unread_comment_count'], 'new', 'new')) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
