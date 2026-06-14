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

function normalize_comment(array $comment): array
{
    return [
        'id' => (int) $comment['id'],
        'event_id' => (int) $comment['event_id'],
        'comment_text' => (string) $comment['comment_text'],
        'is_read_by_admin' => (bool) $comment['is_read_by_admin'],
        'created_at' => (string) $comment['created_at'],
        'read_at' => $comment['read_at'] === null ? null : (string) $comment['read_at'],
    ];
}

function fetch_comments_for_event(int $eventId): array
{
    $statement = db()->prepare(
        'SELECT id, event_id, comment_text, is_read_by_admin, created_at, read_at
        FROM comments
        WHERE event_id = :event_id
        ORDER BY created_at ASC, id ASC'
    );

    $statement->execute(['event_id' => $eventId]);

    return array_map('normalize_comment', $statement->fetchAll());
}

function mark_event_comments_read(int $eventId): int
{
    $statement = db()->prepare(
        'UPDATE comments
        SET is_read_by_admin = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
        WHERE event_id = :event_id AND is_read_by_admin = 0'
    );

    $statement->execute(['event_id' => $eventId]);

    return $statement->rowCount();
}

function comment_stats_for_event(int $eventId): array
{
    $statement = db()->prepare(
        'SELECT
            COUNT(*) AS comment_count,
            SUM(CASE WHEN is_read_by_admin = 0 THEN 1 ELSE 0 END) AS unread_comment_count
        FROM comments
        WHERE event_id = :event_id'
    );

    $statement->execute(['event_id' => $eventId]);
    $stats = $statement->fetch();

    return [
        'comment_count' => (int) ($stats['comment_count'] ?? 0),
        'unread_comment_count' => (int) ($stats['unread_comment_count'] ?? 0),
    ];
}

function comment_detail_payload(array $comment): array
{
    return [
        'id' => (int) $comment['id'],
        'eventId' => (int) $comment['event_id'],
        'text' => (string) $comment['comment_text'],
        'createdAt' => (string) $comment['created_at'],
        'readAt' => $comment['read_at'] === null ? null : (string) $comment['read_at'],
        'isReadByAdmin' => (bool) $comment['is_read_by_admin'],
        'isUnread' => !(bool) $comment['is_read_by_admin'],
    ];
}

function comment_stats_payload(array $stats): array
{
    return [
        'commentCount' => (int) $stats['comment_count'],
        'unreadCommentCount' => (int) $stats['unread_comment_count'],
    ];
}
