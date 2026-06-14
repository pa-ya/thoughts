<?php

declare(strict_types=1);

require_once __DIR__ . '/../thoughts-api/src/auth.php';
require_once __DIR__ . '/../thoughts-api/src/events.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('dashboard.php');
}

require_admin_user();

$rawInput = [
    'event_id' => (string) ($_POST['event_id'] ?? ''),
    'event_date' => (string) ($_POST['event_date'] ?? ''),
    'event_text' => (string) ($_POST['event_text'] ?? ''),
    'thoughts' => (string) ($_POST['thoughts'] ?? ''),
    'physical_effect' => (string) ($_POST['physical_effect'] ?? ''),
    'feeling_rate' => (string) ($_POST['feeling_rate'] ?? ''),
];

function redirect_with_event_error(string $message): never
{
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => $message,
    ];
    redirect_to('dashboard.php');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    redirect_with_event_error('The form expired. Try again.');
}

$eventId = validate_event_id($rawInput['event_id']);

if ($eventId === null) {
    redirect_with_event_error('Choose a valid event.');
}

$validation = validate_event_input($rawInput);

if ($validation['errors'] !== []) {
    $firstError = 'The event could not be updated.';

    foreach ($validation['errors'] as $error) {
        if (is_string($error)) {
            $firstError = $error;
            break;
        }
    }

    redirect_with_event_error($firstError);
}

try {
    if (!event_exists($eventId)) {
        redirect_with_event_error('The selected event could not be found.');
    }

    update_event($eventId, $validation['data']);
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Event updated.',
    ];
} catch (Throwable $exception) {
    error_log('Event update failed: ' . $exception->getMessage());
    redirect_with_event_error('The event could not be updated. Check the database connection and schema.');
}

redirect_to('dashboard.php');
