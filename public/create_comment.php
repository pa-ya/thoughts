<?php

declare(strict_types=1);

require_once __DIR__ . '/../thoughts-api/src/auth.php';
require_once __DIR__ . '/../thoughts-api/src/comments.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('dashboard.php');
}

$role = require_authenticated_user();

if ($role !== ROLE_VIEWER) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$rawInput = [
    'event_id' => (string) ($_POST['event_id'] ?? ''),
    'comment_text' => (string) ($_POST['comment_text'] ?? ''),
];

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'The form expired. Try again.',
    ];
    redirect_to('dashboard.php');
}

$validation = validate_comment_input($rawInput);

if ($validation['errors'] !== []) {
    $firstError = 'Comment could not be saved.';

    foreach ($validation['errors'] as $error) {
        if (is_string($error)) {
            $firstError = $error;
            break;
        }
    }

    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => $firstError,
    ];
    redirect_to('dashboard.php');
}

try {
    if (!comment_event_exists((int) $validation['data']['event_id'])) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'The selected event could not be found.',
        ];
        redirect_to('dashboard.php');
    }

    create_comment($validation['data']);
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Comment added.',
    ];
} catch (Throwable $exception) {
    error_log('Comment creation failed: ' . $exception->getMessage());
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'The comment could not be saved. Check the database connection and schema.',
    ];
}

redirect_to('dashboard.php');
