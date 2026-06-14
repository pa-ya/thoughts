<?php

declare(strict_types=1);

require_once __DIR__ . '/../thoughts-api/src/auth.php';
require_once __DIR__ . '/../thoughts-api/src/events.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('dashboard.php');
}

require_admin_user();

function redirect_with_delete_error(string $message): never
{
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => $message,
    ];
    redirect_to('dashboard.php');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    redirect_with_delete_error('The form expired. Try again.');
}

$eventId = validate_event_id($_POST['event_id'] ?? null);

if ($eventId === null) {
    redirect_with_delete_error('Choose a valid event.');
}

try {
    if (!delete_event($eventId)) {
        redirect_with_delete_error('The selected event could not be found.');
    }

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Event deleted.',
    ];
} catch (Throwable $exception) {
    error_log('Event deletion failed: ' . $exception->getMessage());
    redirect_with_delete_error('The event could not be deleted. Check the database connection and schema.');
}

redirect_to('dashboard.php');
