<?php

declare(strict_types=1);

require_once __DIR__ . '/../thoughts-api/src/auth.php';
require_once __DIR__ . '/../thoughts-api/src/events.php';
require_once __DIR__ . '/../thoughts-api/src/comments.php';
require_once __DIR__ . '/../thoughts-api/src/settings.php';

start_app_session();

$appName = (string) config_get('app.name', 'Thoughts Timeline');
$currentRole = require_authenticated_user();
$databaseStatus = database_health_check();
$timelineGroups = [];
$timelineError = null;
$visualSettings = default_visual_settings();
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
        $visualSettings = fetch_visual_settings();
    } catch (Throwable $exception) {
        error_log('Settings query failed: ' . $exception->getMessage());
    }

    try {
        $timelineGroups = group_events_by_month_day(fetch_timeline_events());
    } catch (Throwable $exception) {
        error_log('Timeline query failed: ' . $exception->getMessage());
        $timelineError = 'Timeline data is not available. Make sure the database schema has been imported.';
    }
}

$eventModalOpen = $currentRole === ROLE_ADMIN
    && (($eventFormErrors !== []) || (string) ($_GET['event_modal'] ?? '') === '1');
$settingsModalOpen = $currentRole === ROLE_ADMIN
    && (string) ($_GET['settings_modal'] ?? '') === '1';

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

function selected_if(string $actual, string $expected): string
{
    return $actual === $expected ? 'selected' : '';
}

function checked_if(string $actual, string $expected): string
{
    return $actual === $expected ? 'checked' : '';
}

function event_detail_json(array $event): string
{
    return json_encode(
        event_detail_payload($event),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}
?>
<!doctype html>
<html lang="en" style="<?= e(visual_settings_style_attribute($visualSettings)) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | <?= e($appName) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body
    data-theme="<?= e($visualSettings['theme_mode']) ?>"
    data-density="<?= e($visualSettings['density']) ?>"
>
    <main class="dashboard-shell">
        <header class="dashboard-header">
            <div>
                <p class="eyebrow">Timeline</p>
                <h1><?= e($appName) ?></h1>
            </div>
            <div class="header-actions">
                <?php if ($currentRole === ROLE_ADMIN): ?>
                    <button class="button button-secondary button-icon" type="button" data-modal-open="settings-modal" aria-label="Open settings">
                        <span aria-hidden="true">*</span>
                        <span>Settings</span>
                    </button>
                    <button class="button button-icon" type="button" data-modal-open="event-modal" aria-label="Add event">
                        <span aria-hidden="true">+</span>
                        <span>Add Event</span>
                    </button>
                <?php endif; ?>
                <span class="role-pill"><?= e(ucfirst($currentRole)) ?></span>
                <form class="logout-form" method="post" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="button button-secondary" type="submit">Logout</button>
                </form>
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
                                                <?php $eventPreview = event_preview((string) $event['event_text']); ?>
                                                <article
                                                    class="event-item event-detail-trigger"
                                                    role="button"
                                                    tabindex="0"
                                                    aria-label="Open event details: <?= e($eventPreview) ?>"
                                                    data-event-detail="<?= e(event_detail_json($event)) ?>"
                                                >
                                                    <div class="event-main">
                                                        <h3 dir="auto"><?= e($eventPreview) ?></h3>
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
                                                            <span class="comment-badge" data-comment-count-badge>
                                                                <?= e(pluralize_count((int) $event['comment_count'], 'comment', 'comments')) ?>
                                                            </span>
                                                        <?php endif; ?>

                                                        <?php if ($currentRole === ROLE_ADMIN && (int) $event['unread_comment_count'] > 0): ?>
                                                            <span class="comment-badge comment-badge-new" data-unread-comment-badge>
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
                        <textarea id="event_text" name="event_text" maxlength="<?= EVENT_TEXT_MAX_LENGTH ?>" rows="4" dir="auto" required><?= e(old_event_value($eventFormOld, 'event_text')) ?></textarea>
                        <?php if (($error = field_error($eventFormErrors, 'event_text')) !== null): ?>
                            <p class="input-error"><?= e($error) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="thoughts">Thoughts</label>
                        <textarea id="thoughts" name="thoughts" maxlength="<?= EVENT_TEXT_MAX_LENGTH ?>" rows="4" dir="auto" required><?= e(old_event_value($eventFormOld, 'thoughts')) ?></textarea>
                        <?php if (($error = field_error($eventFormErrors, 'thoughts')) !== null): ?>
                            <p class="input-error"><?= e($error) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="physical_effect">Physical Effect</label>
                        <textarea id="physical_effect" name="physical_effect" maxlength="<?= EVENT_TEXT_MAX_LENGTH ?>" rows="4" dir="auto" required><?= e(old_event_value($eventFormOld, 'physical_effect')) ?></textarea>
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

    <?php if ($currentRole === ROLE_ADMIN): ?>
        <div
            class="modal-backdrop"
            id="settings-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="settings-modal-title"
            aria-hidden="<?= $settingsModalOpen ? 'false' : 'true' ?>"
            data-modal
            <?= $settingsModalOpen ? '' : 'hidden' ?>
        >
            <section class="modal-panel" data-modal-panel>
                <div class="modal-header">
                    <div>
                        <p class="eyebrow">Appearance</p>
                        <h2 id="settings-modal-title">Settings</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">x</button>
                </div>

                <form class="event-form settings-form" method="post" action="update_settings.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <fieldset class="settings-group">
                        <legend>Theme</legend>
                        <div class="segmented-options">
                            <?php foreach (visual_theme_mode_options() as $value => $label): ?>
                                <label>
                                    <input
                                        type="radio"
                                        name="theme_mode"
                                        value="<?= e($value) ?>"
                                        <?= checked_if((string) $visualSettings['theme_mode'], (string) $value) ?>
                                    >
                                    <span><?= e($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="settings-grid">
                        <div class="form-field">
                            <label for="accent_color">Accent Color</label>
                            <div class="color-control">
                                <input
                                    id="accent_color"
                                    name="accent_color"
                                    type="color"
                                    value="<?= e($visualSettings['accent_color']) ?>"
                                    aria-label="Accent color"
                                >
                                <input
                                    name="accent_color_text"
                                    type="text"
                                    value="<?= e($visualSettings['accent_color']) ?>"
                                    aria-label="Accent color value"
                                    data-color-text
                                    readonly
                                >
                            </div>
                        </div>

                        <div class="form-field">
                            <label for="font_family">Font Family</label>
                            <select id="font_family" name="font_family">
                                <?php foreach (visual_font_family_options() as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= selected_if((string) $visualSettings['font_family'], (string) $value) ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="settings-grid">
                        <div class="form-field">
                            <label for="base_font_size">Base Font Size</label>
                            <div class="range-control">
                                <input
                                    id="base_font_size"
                                    name="base_font_size"
                                    type="range"
                                    min="14"
                                    max="20"
                                    step="1"
                                    value="<?= e($visualSettings['base_font_size']) ?>"
                                    data-range-control="base_font_size_number"
                                >
                                <input
                                    id="base_font_size_number"
                                    type="number"
                                    min="14"
                                    max="20"
                                    step="1"
                                    value="<?= e($visualSettings['base_font_size']) ?>"
                                    data-range-value="base_font_size"
                                    aria-label="Base font size value"
                                >
                            </div>
                        </div>

                        <fieldset class="settings-group settings-group-inline">
                            <legend>Density</legend>
                            <div class="segmented-options">
                                <?php foreach (visual_density_options() as $value => $label): ?>
                                    <label>
                                        <input
                                            type="radio"
                                            name="density"
                                            value="<?= e($value) ?>"
                                            <?= checked_if((string) $visualSettings['density'], (string) $value) ?>
                                        >
                                        <span><?= e($label) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    </div>

                    <div class="modal-actions">
                        <button class="button button-secondary" type="button" data-modal-close>Cancel</button>
                        <button class="button" type="submit">Save Settings</button>
                    </div>
                </form>
            </section>
        </div>
    <?php endif; ?>

    <div
        class="modal-backdrop"
        id="event-detail-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="event-detail-title"
        aria-hidden="true"
        data-modal
        data-event-detail-modal
        <?php if ($currentRole === ROLE_ADMIN): ?>
            data-comments-url="event_comments.php"
            data-comments-csrf="<?= e(csrf_token()) ?>"
        <?php endif; ?>
        hidden
    >
        <section class="modal-panel modal-panel-wide" data-modal-panel>
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Event Detail</p>
                    <h2 id="event-detail-title" data-detail-field="eventText" dir="auto">Event</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Close modal">x</button>
            </div>

            <dl class="detail-stats" aria-label="Event summary" data-detail-view>
                <div>
                    <dt>Date</dt>
                    <dd data-detail-field="eventDateLabel"></dd>
                </div>
                <div>
                    <dt>Feeling</dt>
                    <dd><span data-detail-field="feelingRate"></span>/10</dd>
                </div>
                <div>
                    <dt>Comments</dt>
                    <dd data-detail-field="commentSummary"></dd>
                </div>
            </dl>

            <div class="detail-grid" data-detail-view>
                <section class="detail-section">
                    <h3>Thoughts</h3>
                    <p class="detail-text" data-detail-field="thoughts" dir="auto"></p>
                </section>
                <section class="detail-section">
                    <h3>Physical Effect</h3>
                    <p class="detail-text" data-detail-field="physicalEffect" dir="auto"></p>
                </section>
            </div>

            <?php if ($currentRole === ROLE_ADMIN): ?>
                <section class="detail-section detail-comments" data-detail-view>
                    <h3>Comments</h3>
                    <div class="comments-list" data-admin-comments-list aria-live="polite">
                        <p class="detail-text" data-detail-field="adminCommentsPreview"></p>
                    </div>
                </section>

                <div class="modal-actions detail-actions" data-detail-view>
                    <button class="button button-secondary" type="button" data-edit-event>Edit</button>
                    <form class="inline-form" method="post" action="delete_event.php" data-confirm="Delete this event? This cannot be undone.">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="event_id" value="" data-event-id-input>
                        <button class="button button-danger" type="submit">Delete</button>
                    </form>
                </div>

                <form class="event-form edit-event-form" method="post" action="update_event.php" data-edit-event-form hidden novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="event_id" value="" data-event-id-input data-edit-field="id">

                    <div class="form-field">
                        <label for="edit_event_date">Date</label>
                        <input
                            id="edit_event_date"
                            name="event_date"
                            type="date"
                            data-edit-field="eventDate"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="edit_event_text">Event</label>
                        <textarea
                            id="edit_event_text"
                            name="event_text"
                            maxlength="<?= EVENT_TEXT_MAX_LENGTH ?>"
                            rows="4"
                            data-edit-field="eventText"
                            dir="auto"
                            required
                        ></textarea>
                    </div>

                    <div class="form-field">
                        <label for="edit_thoughts">Thoughts</label>
                        <textarea
                            id="edit_thoughts"
                            name="thoughts"
                            maxlength="<?= EVENT_TEXT_MAX_LENGTH ?>"
                            rows="4"
                            data-edit-field="thoughts"
                            dir="auto"
                            required
                        ></textarea>
                    </div>

                    <div class="form-field">
                        <label for="edit_physical_effect">Physical Effect</label>
                        <textarea
                            id="edit_physical_effect"
                            name="physical_effect"
                            maxlength="<?= EVENT_TEXT_MAX_LENGTH ?>"
                            rows="4"
                            data-edit-field="physicalEffect"
                            dir="auto"
                            required
                        ></textarea>
                    </div>

                    <div class="form-field">
                        <label for="edit_feeling_rate">Feeling Rate</label>
                        <input
                            id="edit_feeling_rate"
                            name="feeling_rate"
                            type="number"
                            min="0"
                            max="10"
                            step="0.01"
                            data-edit-field="feelingRate"
                            required
                        >
                    </div>

                    <div class="modal-actions detail-actions">
                        <button class="button button-secondary" type="button" data-edit-cancel>Cancel</button>
                        <button class="button" type="submit">Save Changes</button>
                    </div>
                </form>
            <?php else: ?>
                <form class="event-form comment-form" method="post" action="create_comment.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="event_id" value="" data-event-id-input>
                    <div class="form-field">
                        <label for="detail-comment">Comment</label>
                        <textarea id="detail-comment" name="comment_text" maxlength="<?= COMMENT_TEXT_MAX_LENGTH ?>" rows="4" dir="auto" required></textarea>
                    </div>
                    <div class="modal-actions detail-actions">
                        <button class="button" type="submit">Send Comment</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
