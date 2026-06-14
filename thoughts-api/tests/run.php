<?php

declare(strict_types=1);

$root = dirname(__DIR__);

putenv('APP_CONFIG_FILE=/tmp/thoughts-tests-no-local-config.php');
putenv('APP_NAME=Test Thoughts Timeline');
putenv('APP_TIMEZONE=UTC');
putenv('APP_SECURE_COOKIES=false');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3307');
putenv('DB_NAME=thoughts_test');
putenv('DB_USER=test_user');
putenv('DB_PASSWORD=test_password');
putenv('DB_CHARSET=utf8mb4');
putenv('DB_COLLATION=utf8mb4_unicode_ci');
putenv('ADMIN_PASSWORD_HASH=' . password_hash('admin-secret', PASSWORD_DEFAULT));
putenv('VIEWER_PASSWORD_HASH=' . password_hash('viewer-secret', PASSWORD_DEFAULT));

require_once $root . '/src/auth.php';
require_once $root . '/src/events.php';
require_once $root . '/src/comments.php';
require_once $root . '/src/settings.php';

$tests = [];

function test(string $name, callable $callback): void
{
    global $tests;

    $tests[] = [$name, $callback];
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s Expected %s, got %s.',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assert_null($actual, string $message): void
{
    if ($actual !== null) {
        throw new RuntimeException(sprintf('%s Expected null, got %s.', $message, var_export($actual, true)));
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Missing: ' . $needle);
    }
}

test('configuration loads deterministic test values', function (): void {
    assert_same('Test Thoughts Timeline', config_get('app.name'), 'App name should come from the environment.');
    assert_same('UTC', config_get('app.timezone'), 'Timezone should come from the environment.');
    assert_same(false, config_get('app.secure_cookies'), 'Secure cookie flag should parse false.');
    assert_same(3307, config_get('database.port'), 'Database port should be cast to an integer.');
    assert_same('utf8mb4', config_get('database.charset'), 'Database charset should support Persian and emojis.');
    assert_same('utf8mb4_unicode_ci', config_get('database.collation'), 'Database collation should use a portable utf8mb4 Unicode collation.');
    assert_same('fallback', config_get('missing.path', 'fallback'), 'Missing config path should return the fallback.');
});

test('password configuration and role resolution work', function (): void {
    assert_true(auth_is_configured(), 'Auth should be configured when both password hashes are present.');
    assert_same(ROLE_ADMIN, role_from_password('admin-secret'), 'Admin password should resolve to admin role.');
    assert_same(ROLE_VIEWER, role_from_password('viewer-secret'), 'Viewer password should resolve to viewer role.');
    assert_null(role_from_password('wrong-secret'), 'Wrong password should not resolve to a role.');
});

test('session role helpers accept only known roles', function (): void {
    start_app_session();
    $_SESSION = [];

    assert_null(current_role(), 'Empty session should have no role.');

    $_SESSION['role'] = 'invalid';
    assert_null(current_role(), 'Unknown role should be ignored.');

    sign_in_as(ROLE_ADMIN);
    assert_same(ROLE_ADMIN, current_role(), 'Admin sign-in should set admin role.');
    assert_true(is_admin(), 'Admin helper should be true after admin sign-in.');
    assert_true(!is_viewer(), 'Viewer helper should be false after admin sign-in.');

    sign_in_as(ROLE_VIEWER);
    assert_same(ROLE_VIEWER, current_role(), 'Viewer sign-in should set viewer role.');
    assert_true(is_viewer(), 'Viewer helper should be true after viewer sign-in.');

    $thrown = false;

    try {
        sign_in_as('editor');
    } catch (InvalidArgumentException) {
        $thrown = true;
    }

    assert_true($thrown, 'Unknown sign-in role should throw.');
});

test('csrf token is stable and verifiable inside a session', function (): void {
    start_app_session();
    $_SESSION = [];

    $token = csrf_token();

    assert_true($token !== '', 'CSRF token should not be empty.');
    assert_same($token, csrf_token(), 'CSRF token should be stable within one session.');
    assert_true(verify_csrf_token($token), 'Current CSRF token should verify.');
    assert_true(!verify_csrf_token('wrong-token'), 'Wrong CSRF token should fail.');
    assert_true(!verify_csrf_token(null), 'Missing CSRF token should fail.');
});

test('login throttle limits repeated failed attempts and can be cleared', function (): void {
    start_app_session();
    $_SESSION = [];

    $limits = login_throttle_limits();
    $now = 1000;

    assert_true(!login_throttle_is_limited($now), 'Fresh sessions should not be login-limited.');

    for ($attempt = 1; $attempt < $limits['max_attempts']; $attempt++) {
        login_throttle_register_failure($now + $attempt);
        assert_true(!login_throttle_is_limited($now + $attempt), 'Throttle should wait until the max attempt is reached.');
    }

    login_throttle_register_failure($now + $limits['max_attempts']);

    assert_true(login_throttle_is_limited($now + $limits['max_attempts']), 'Throttle should lock after the configured max attempts.');
    assert_same(
        $limits['lockout_seconds'],
        login_throttle_remaining_seconds($now + $limits['max_attempts']),
        'Remaining lockout time should match the configured lockout seconds.'
    );

    login_throttle_clear();

    assert_true(!login_throttle_is_limited($now + $limits['max_attempts']), 'Clearing throttle should remove the lock.');
});

test('login throttle resets after the failed-attempt window expires', function (): void {
    start_app_session();
    $_SESSION = [];

    $limits = login_throttle_limits();
    $now = 2000;

    for ($attempt = 0; $attempt < $limits['max_attempts']; $attempt++) {
        login_throttle_register_failure($now + $attempt);
    }

    assert_true(login_throttle_is_limited($now + $limits['max_attempts']), 'Throttle should be active before the window expires.');

    $afterWindow = $now + $limits['window_seconds'] + $limits['lockout_seconds'] + 1;

    assert_true(!login_throttle_is_limited($afterWindow), 'Throttle should reset after the failed-attempt window expires.');
    assert_same(
        ['failed_count' => 0, 'first_failed_at' => 0, 'locked_until' => 0],
        login_throttle_state($afterWindow),
        'Expired throttle state should normalize to an empty state.'
    );
});

test('domain constants match field requirements', function (): void {
    assert_same(1024, event_field_max_length(), 'Event text fields should allow 1024 characters.');
    assert_same(['min' => 0.0, 'max' => 10.0], feeling_rate_bounds(), 'Feeling rate bounds should be 0 to 10.');
    assert_same(1024, comment_field_max_length(), 'Comment field should allow 1024 characters.');
});

test('visual settings validation accepts safe values and normalizes data', function (): void {
    $result = validate_visual_settings_input([
        'theme_mode' => 'dark',
        'accent_color' => '#ABCDEF',
        'base_font_size' => '18',
        'font_family' => 'serif',
        'density' => 'compact',
    ]);

    assert_same([], $result['errors'], 'Valid visual settings should have no errors.');
    assert_same('dark', $result['data']['theme_mode'], 'Theme mode should be preserved.');
    assert_same('#abcdef', $result['data']['accent_color'], 'Accent color should normalize to lowercase.');
    assert_same('18', $result['data']['base_font_size'], 'Font size should normalize to string integer.');
    assert_same('serif', $result['data']['font_family'], 'Font family should be preserved.');
    assert_same('compact', $result['data']['density'], 'Density should be preserved.');
});

test('visual settings validation rejects unsafe values', function (): void {
    $result = validate_visual_settings_input([
        'theme_mode' => 'neon',
        'accent_color' => 'javascript:alert(1)',
        'base_font_size' => '30',
        'font_family' => 'remote',
        'density' => 'tiny',
    ]);

    assert_true(isset($result['errors']['theme_mode']), 'Unknown theme mode should be rejected.');
    assert_true(isset($result['errors']['accent_color']), 'Unsafe accent color should be rejected.');
    assert_true(isset($result['errors']['base_font_size']), 'Out-of-range font size should be rejected.');
    assert_true(isset($result['errors']['font_family']), 'Unknown font family should be rejected.');
    assert_true(isset($result['errors']['density']), 'Unknown density should be rejected.');
});

test('visual settings style attribute exposes CSS custom properties', function (): void {
    $style = visual_settings_style_attribute([
        'theme_mode' => 'light',
        'accent_color' => '#10b981',
        'base_font_size' => '17',
        'font_family' => 'mono',
        'density' => 'comfortable',
    ]);

    assert_contains('--accent: #10b981', $style, 'Style should include accent color.');
    assert_contains('--accent-strong:', $style, 'Style should include derived strong accent.');
    assert_contains('--custom-base-font-size: 17px', $style, 'Style should include base font size.');
    assert_contains('monospace', $style, 'Style should include safe font stack.');
});

test('event id validation accepts only positive integers', function (): void {
    assert_same(12, validate_event_id('12'), 'Positive numeric event id should be accepted.');
    assert_same(12, validate_event_id(12), 'Positive integer event id should be accepted.');
    assert_null(validate_event_id('0'), 'Zero event id should be rejected.');
    assert_null(validate_event_id('-4'), 'Negative event id should be rejected.');
    assert_null(validate_event_id('abc'), 'Non-numeric event id should be rejected.');
});

test('comment validation accepts valid input and normalizes data', function (): void {
    $result = validate_comment_input([
        'event_id' => '12',
        'comment_text' => '  A useful comment  ',
    ]);

    assert_same([], $result['errors'], 'Valid comment input should have no errors.');
    assert_same(12, $result['data']['event_id'], 'Event id should be normalized to an integer.');
    assert_same('A useful comment', $result['data']['comment_text'], 'Comment text should be trimmed.');
});

test('comment validation rejects invalid input', function (): void {
    $result = validate_comment_input([
        'event_id' => '0',
        'comment_text' => '',
    ]);

    assert_true(isset($result['errors']['event_id']), 'Invalid event id should be rejected.');
    assert_true(isset($result['errors']['comment_text']), 'Empty comment should be rejected.');

    $longResult = validate_comment_input([
        'event_id' => '1',
        'comment_text' => str_repeat('a', 1025),
    ]);

    assert_true(isset($longResult['errors']['comment_text']), 'Overlong comments should be rejected.');
});

test('comment payload helpers expose admin review values', function (): void {
    $comment = normalize_comment([
        'id' => '9',
        'event_id' => '3',
        'comment_text' => "Viewer note\nwith detail",
        'is_read_by_admin' => '0',
        'created_at' => '2026-06-14 09:30:00',
        'read_at' => null,
    ]);

    assert_same(9, $comment['id'], 'Comment id should normalize to int.');
    assert_same(3, $comment['event_id'], 'Event id should normalize to int.');
    assert_same(false, $comment['is_read_by_admin'], 'Unread flag should normalize to false.');
    assert_null($comment['read_at'], 'Unread comments should preserve null read time.');

    $payload = comment_detail_payload($comment);

    assert_same(9, $payload['id'], 'Payload should include comment id.');
    assert_same(3, $payload['eventId'], 'Payload should include event id.');
    assert_same("Viewer note\nwith detail", $payload['text'], 'Payload should preserve comment text.');
    assert_same(false, $payload['isReadByAdmin'], 'Payload should include read state.');
    assert_same(true, $payload['isUnread'], 'Payload should expose unread state for highlighting.');

    assert_same(
        ['commentCount' => 2, 'unreadCommentCount' => 0],
        comment_stats_payload(['comment_count' => '2', 'unread_comment_count' => '0']),
        'Stats payload should use camel-case integer counts.'
    );
});

test('event validation accepts valid input and normalizes data', function (): void {
    $result = validate_event_input([
        'event_date' => '2026-06-12',
        'event_text' => '  A useful event  ',
        'thoughts' => '  Clear thoughts  ',
        'physical_effect' => '  Calm breathing  ',
        'feeling_rate' => '7.5',
    ]);

    assert_same([], $result['errors'], 'Valid event input should have no errors.');
    assert_same('2026-06-12', $result['data']['event_date'], 'Date should be preserved.');
    assert_same('A useful event', $result['data']['event_text'], 'Event text should be trimmed.');
    assert_same('Clear thoughts', $result['data']['thoughts'], 'Thoughts should be trimmed.');
    assert_same('Calm breathing', $result['data']['physical_effect'], 'Physical effect should be trimmed.');
    assert_same('7.50', $result['data']['feeling_rate'], 'Feeling rate should be normalized for DECIMAL storage.');
});

test('event validation rejects invalid input', function (): void {
    $result = validate_event_input([
        'event_date' => '2026-02-30',
        'event_text' => '',
        'thoughts' => str_repeat('a', 1025),
        'physical_effect' => 'Effect',
        'feeling_rate' => '10.01',
    ]);

    assert_true(isset($result['errors']['event_date']), 'Invalid date should be rejected.');
    assert_true(isset($result['errors']['event_text']), 'Empty event should be rejected.');
    assert_true(isset($result['errors']['thoughts']), 'Overlong thoughts should be rejected.');
    assert_true(isset($result['errors']['feeling_rate']), 'Out-of-range rate should be rejected.');

    $precisionResult = validate_event_input([
        'event_date' => '2026-06-12',
        'event_text' => 'Event',
        'thoughts' => 'Thoughts',
        'physical_effect' => 'Effect',
        'feeling_rate' => '7.123',
    ]);

    assert_true(isset($precisionResult['errors']['feeling_rate']), 'Rates with more than 2 decimals should be rejected.');
});

test('timeline grouping nests events by month and day', function (): void {
    $groups = group_events_by_month_day([
        [
            'id' => 3,
            'event_date' => '2026-07-02',
            'event_text' => 'July event',
            'thoughts' => 'Thoughts',
            'physical_effect' => 'Effect',
            'feeling_rate' => 8.5,
            'created_at' => '2026-07-02 12:00:00',
            'updated_at' => '2026-07-02 12:00:00',
            'comment_count' => 2,
            'unread_comment_count' => 1,
        ],
        [
            'id' => 2,
            'event_date' => '2026-06-12',
            'event_text' => 'Second June event',
            'thoughts' => 'Thoughts',
            'physical_effect' => 'Effect',
            'feeling_rate' => 4.0,
            'created_at' => '2026-06-12 11:00:00',
            'updated_at' => '2026-06-12 11:00:00',
            'comment_count' => 0,
            'unread_comment_count' => 0,
        ],
        [
            'id' => 1,
            'event_date' => '2026-06-12',
            'event_text' => 'First June event',
            'thoughts' => 'Thoughts',
            'physical_effect' => 'Effect',
            'feeling_rate' => 3.25,
            'created_at' => '2026-06-12 10:00:00',
            'updated_at' => '2026-06-12 10:00:00',
            'comment_count' => 1,
            'unread_comment_count' => 0,
        ],
    ]);

    assert_same(2, count($groups), 'Events should be split into two month groups.');
    assert_same('July 2026', $groups[0]['label'], 'First month should preserve input order.');
    assert_same(1, $groups[0]['event_count'], 'July should have one event.');
    assert_same('Thursday, July 2, 2026', $groups[0]['days'][0]['label'], 'July day label should be readable.');
    assert_same(2, $groups[1]['event_count'], 'June should have two events.');
    assert_same(1, count($groups[1]['days']), 'June events on the same date should share one day group.');
    assert_same(2, count($groups[1]['days'][0]['events']), 'June day should contain both events.');
    assert_same('June 12, 2026', $groups[1]['days'][0]['events'][0]['event_date_label'], 'Event should receive display date label.');
});

test('event detail payload exposes modal-ready values', function (): void {
    $payload = event_detail_payload([
        'id' => 5,
        'event_date' => '2026-06-12',
        'event_date_label' => 'June 12, 2026',
        'event_text' => 'Full event text',
        'thoughts' => "Line one\nLine two",
        'physical_effect' => 'Steady breathing',
        'feeling_rate' => 7.5,
        'comment_count' => 2,
        'unread_comment_count' => 1,
    ]);

    assert_same(5, $payload['id'], 'Payload should include event id.');
    assert_same('2026-06-12', $payload['eventDate'], 'Payload should include machine-readable date.');
    assert_same('June 12, 2026', $payload['eventDateLabel'], 'Payload should include display date.');
    assert_same('Full event text', $payload['eventText'], 'Payload should include full event text.');
    assert_same("Line one\nLine two", $payload['thoughts'], 'Payload should preserve long text formatting.');
    assert_same('Steady breathing', $payload['physicalEffect'], 'Payload should include physical effect.');
    assert_same('7.5', $payload['feelingRate'], 'Payload should include formatted feeling rate.');
    assert_same(2, $payload['commentCount'], 'Payload should include total comments.');
    assert_same(1, $payload['unreadCommentCount'], 'Payload should include unread comments.');
});

test('feeling rates are formatted without unnecessary trailing zeros', function (): void {
    assert_same('0', format_feeling_rate(0.0), 'Zero should format cleanly.');
    assert_same('3.25', format_feeling_rate(3.25), 'Fractional rates should be preserved.');
    assert_same('8.5', format_feeling_rate(8.5), 'Single decimal rates should not keep a trailing zero.');
    assert_same('10', format_feeling_rate(10.0), 'Ten should format cleanly.');
});

test('schema includes required tables, constraints, and defaults', function () use ($root): void {
    $schema = file_get_contents($root . '/database/schema.sql');

    if ($schema === false) {
        throw new RuntimeException('Could not read thoughts-api/database/schema.sql.');
    }

    assert_contains('CREATE TABLE IF NOT EXISTS events', $schema, 'Schema should create events table.');
    assert_contains('CREATE TABLE IF NOT EXISTS comments', $schema, 'Schema should create comments table.');
    assert_contains('CREATE TABLE IF NOT EXISTS settings', $schema, 'Schema should create settings table.');
    assert_contains('ON DELETE CASCADE', $schema, 'Comments should cascade when an event is deleted.');
    assert_contains('CHAR_LENGTH(event_text) <= 1024', $schema, 'Event text length should be constrained.');
    assert_contains('CHAR_LENGTH(thoughts) <= 1024', $schema, 'Thoughts length should be constrained.');
    assert_contains('CHAR_LENGTH(physical_effect) <= 1024', $schema, 'Physical effect length should be constrained.');
    assert_contains('feeling_rate >= 0 AND feeling_rate <= 10', $schema, 'Feeling rate range should be constrained.');
    assert_contains("('theme_mode', 'system')", $schema, 'Schema should seed theme mode setting.');
});

$failures = [];

foreach ($tests as [$name, $callback]) {
    try {
        $callback();
    } catch (Throwable $throwable) {
        $failures[] = [$name, $throwable->getMessage()];
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

if ($failures !== []) {
    echo "Tests failed:\n";

    foreach ($failures as [$name, $message]) {
        echo "- {$name}: {$message}\n";
    }

    exit(1);
}

echo sprintf("OK (%d tests)\n", count($tests));
