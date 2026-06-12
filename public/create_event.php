<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/events.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('dashboard.php');
}

require_admin_user();

$rawInput = [
    'event_date' => (string) ($_POST['event_date'] ?? ''),
    'event_text' => (string) ($_POST['event_text'] ?? ''),
    'thoughts' => (string) ($_POST['thoughts'] ?? ''),
    'physical_effect' => (string) ($_POST['physical_effect'] ?? ''),
    'feeling_rate' => (string) ($_POST['feeling_rate'] ?? ''),
];

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $_SESSION['event_form_errors'] = ['form' => 'The form expired. Try again.'];
    $_SESSION['event_form_old'] = $rawInput;
    redirect_to('dashboard.php?event_modal=1');
}

$validation = validate_event_input($rawInput);

if ($validation['errors'] !== []) {
    $_SESSION['event_form_errors'] = $validation['errors'];
    $_SESSION['event_form_old'] = $rawInput;
    redirect_to('dashboard.php?event_modal=1');
}

try {
    create_event($validation['data']);
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Event added.',
    ];
} catch (Throwable $exception) {
    error_log('Event creation failed: ' . $exception->getMessage());
    $_SESSION['event_form_errors'] = ['form' => 'The event could not be saved. Check the database connection and schema.'];
    $_SESSION['event_form_old'] = $rawInput;
    redirect_to('dashboard.php?event_modal=1');
}

redirect_to('dashboard.php');
