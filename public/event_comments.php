<?php

declare(strict_types=1);

require_once __DIR__ . '/../thoughts-api/src/auth.php';
require_once __DIR__ . '/../thoughts-api/src/comments.php';

start_app_session();

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed.'], 405);
}

$role = current_role();

if ($role === null) {
    json_response(['error' => 'Authentication required.'], 401);
}

if ($role !== ROLE_ADMIN) {
    json_response(['error' => 'Forbidden.'], 403);
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    json_response(['error' => 'The form expired. Try again.'], 403);
}

$eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => [
        'min_range' => 1,
    ],
]);

if ($eventId === false) {
    json_response(['error' => 'Choose a valid event.'], 422);
}

try {
    if (!comment_event_exists((int) $eventId)) {
        json_response(['error' => 'The selected event could not be found.'], 404);
    }

    $comments = fetch_comments_for_event((int) $eventId);
    $markedReadCount = mark_event_comments_read((int) $eventId);
    $stats = comment_stats_for_event((int) $eventId);

    json_response([
        'comments' => array_map('comment_detail_payload', $comments),
        'stats' => comment_stats_payload($stats),
        'markedReadCount' => $markedReadCount,
    ]);
} catch (Throwable $exception) {
    error_log('Comment review failed: ' . $exception->getMessage());
    json_response(['error' => 'Comments could not be loaded.'], 500);
}
