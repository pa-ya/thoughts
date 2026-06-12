<?php

declare(strict_types=1);

require_once __DIR__ . '/../thoughts-api/src/auth.php';
require_once __DIR__ . '/../thoughts-api/src/events.php';

start_app_session();

$appName = (string) config_get('app.name', 'Thoughts Timeline');
$currentRole = require_authenticated_user();
$databaseStatus = database_health_check();
$timelineGroups = [];
$timelineError = null;
$eventFormErrors = $_SESSION['event_form_errors'] ?? [];
$eventFormOld = $_SESSION['event_form_old'] ?? [];
$flash = $_SESSION['flash'] ?? null;

unset($_SESSION['event_form_errors'], $_SESSION['event_form_old'], $_SESSION['flash']);

if (!is_array($eventFormErrors)) {
    $eventFormErrors = [];
}

if (!is_array($eventFormOld)) {
    $eventFormOld = [];
}

if (!is_array($flash)) {
    $flash = null;
}

if ($databaseStatus['ok']) {
    try {
        $timelineGroups = group_events_by_month_day(fetch_timeline_events());
    } catch (Throwable $exception) {
        error_log('Timeline query failed: ' . $exception->getMessage());
        $timelineError = 'Timeline data is not available. Make sure the database schema has been imported.';
    }
}

$eventModalOpen = $currentRole === ROLE_ADMIN
    && (($eventFormErrors !== []) || (string) ($_GET['event_modal'] ?? '') === '1');

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

function old_event_value(array $old, string $field, string $default = ''): string
{
    return (string) ($old[$field] ?? $default);
}

function field_error(array $errors, string $field): ?string
{
    $error = $errors[$field] ?? null;

    return is_string($error) ? $error : null;
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
                <?php if ($currentRole === ROLE_ADMIN): ?>
                    <button class="button button-icon" type="button" data-modal-open="event-modal" aria-label="Add event">
                        <span aria-hidden="true">+</span>
                        <span>Add Event</span>
                    </button>
                <?php endif; ?>
                <span class="role-pill"><?= e(ucfirst($currentRole)) ?></span>
                <a class="button button-secondary" href="logout.php">Logout</a>
            </div>
        </header>

        <?php if ($flash !== null && isset($flash['message'], $flash['type'])): ?>
            <p class="flash flash-<?= e((string) $flash['type']) ?>" role="status">
                <?= e((string) $flash['message']) ?>
            </p>
        <?php endif; ?>

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

    <?php if ($currentRole === ROLE_ADMIN): ?>
        <div
            class="modal-backdrop"
            id="event-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="event-modal-title"
            aria-hidden="<?= $eventModalOpen ? 'false' : 'true' ?>"
            data-modal
            <?= $eventModalOpen ? '' : 'hidden' ?>
        >
            <section class="modal-panel" data-modal-panel>
                <div class="modal-header">
                    <div>
                        <p class="eyebrow">New Record</p>
                        <h2 id="event-modal-title">Add Event</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">x</button>
                </div>

                <?php if (($formError = field_error($eventFormErrors, 'form')) !== null): ?>
                    <p class="alert alert-error" role="alert"><?= e($formError) ?></p>
                <?php endif; ?>

                <form class="event-form" method="post" action="create_event.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="form-field">
                        <label for="event_date">Date</label>
                        <input
                            id="event_date"
                            name="event_date"
                            type="date"
                            value="<?= e(old_event_value($eventFormOld, 'event_date', date('Y-m-d'))) ?>"
                            required
                        >
                        <?php if (($error = field_error($eventFormErrors, 'event_date')) !== null): ?>
                            <p class="input-error"><?= e($error) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="event_text">Event</label>
                        <textarea id="event_text" name="event_text" maxlength="<?= EVENT_TEXT_MAX_LENGTH ?>" rows="4" required><?= e(old_event_value($eventFormOld, 'event_text')) ?></textarea>
                        <?php if (($error = field_error($eventFormErrors, 'event_text')) !== null): ?>
                            <p class="input-error"><?= e($error) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="thoughts">Thoughts</label>
                        <textarea id="thoughts" name="thoughts" maxlength="<?= EVENT_TEXT_MAX_LENGTH ?>" rows="4" required><?= e(old_event_value($eventFormOld, 'thoughts')) ?></textarea>
                        <?php if (($error = field_error($eventFormErrors, 'thoughts')) !== null): ?>
                            <p class="input-error"><?= e($error) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="physical_effect">Physical Effect</label>
                        <textarea id="physical_effect" name="physical_effect" maxlength="<?= EVENT_TEXT_MAX_LENGTH ?>" rows="4" required><?= e(old_event_value($eventFormOld, 'physical_effect')) ?></textarea>
                        <?php if (($error = field_error($eventFormErrors, 'physical_effect')) !== null): ?>
                            <p class="input-error"><?= e($error) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="feeling_rate">Feeling Rate</label>
                        <input
                            id="feeling_rate"
                            name="feeling_rate"
                            type="number"
                            min="0"
                            max="10"
                            step="0.01"
                            value="<?= e(old_event_value($eventFormOld, 'feeling_rate')) ?>"
                            required
                        >
                        <?php if (($error = field_error($eventFormErrors, 'feeling_rate')) !== null): ?>
                            <p class="input-error"><?= e($error) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="modal-actions">
                        <button class="button button-secondary" type="button" data-modal-close>Cancel</button>
                        <button class="button" type="submit">Save Event</button>
                    </div>
                </form>
            </section>
        </div>
    <?php endif; ?>
</body>
</html>
