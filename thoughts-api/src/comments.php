<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

const COMMENT_TEXT_MAX_LENGTH = 1024;

function comment_field_max_length(): int
{
    return COMMENT_TEXT_MAX_LENGTH;
}

function comment_string_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function validate_comment_input(array $input): array
{
    $errors = [];
    $data = [];

    $eventIdRaw = $input['event_id'] ?? null;
    $eventId = filter_var($eventIdRaw, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
        ],
    ]);

    if ($eventId === false) {
        $errors['event_id'] = 'Choose a valid event.';
    } else {
        $data['event_id'] = (int) $eventId;
    }

    $commentText = trim((string) ($input['comment_text'] ?? ''));

    if ($commentText === '') {
        $errors['comment_text'] = 'Comment is required.';
    } elseif (comment_string_length($commentText) > COMMENT_TEXT_MAX_LENGTH) {
        $errors['comment_text'] = 'Comment must be 1024 characters or fewer.';
    } else {
        $data['comment_text'] = $commentText;
    }

    return [
        'data' => $data,
        'errors' => $errors,
    ];
}

function comment_event_exists(int $eventId): bool
{
    $statement = db()->prepare('SELECT 1 FROM events WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $eventId]);

    return $statement->fetchColumn() !== false;
}

function create_comment(array $data): int
{
    $statement = db()->prepare(
        'INSERT INTO comments (event_id, comment_text, is_read_by_admin)
        VALUES (:event_id, :comment_text, 0)'
    );

    $statement->execute([
        'event_id' => $data['event_id'],
        'comment_text' => $data['comment_text'],
    ]);

    return (int) db()->lastInsertId();
}
