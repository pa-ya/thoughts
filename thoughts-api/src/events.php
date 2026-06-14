<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

const EVENT_TEXT_MAX_LENGTH = 1024;
const FEELING_RATE_MIN = 0.0;
const FEELING_RATE_MAX = 10.0;

function event_field_max_length(): int
{
    return EVENT_TEXT_MAX_LENGTH;
}

function feeling_rate_bounds(): array
{
    return [
        'min' => FEELING_RATE_MIN,
        'max' => FEELING_RATE_MAX,
    ];
}

function event_string_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function validate_event_input(array $input): array
{
    $errors = [];
    $data = [];

    $dateRaw = trim((string) ($input['event_date'] ?? ''));
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateRaw);

    if ($dateRaw === '' || !$date || $date->format('Y-m-d') !== $dateRaw) {
        $errors['event_date'] = 'Choose a valid date.';
    } else {
        $data['event_date'] = $dateRaw;
    }

    $textFields = [
        'event_text' => 'Event',
        'thoughts' => 'Thoughts',
        'physical_effect' => 'Physical Effect',
    ];

    foreach ($textFields as $field => $label) {
        $value = trim((string) ($input[$field] ?? ''));

        if ($value === '') {
            $errors[$field] = $label . ' is required.';
            continue;
        }

        if (event_string_length($value) > EVENT_TEXT_MAX_LENGTH) {
            $errors[$field] = $label . ' must be 1024 characters or fewer.';
            continue;
        }

        $data[$field] = $value;
    }

    $rateRaw = trim((string) ($input['feeling_rate'] ?? ''));

    if ($rateRaw === '') {
        $errors['feeling_rate'] = 'Feeling Rate is required.';
    } elseif (!preg_match('/^\d+(?:\.\d{1,2})?$/', $rateRaw)) {
        $errors['feeling_rate'] = 'Feeling Rate must be a number with at most 2 decimal places.';
    } else {
        $rate = (float) $rateRaw;

        if ($rate < FEELING_RATE_MIN || $rate > FEELING_RATE_MAX) {
            $errors['feeling_rate'] = 'Feeling Rate must be between 0 and 10.';
        } else {
            $data['feeling_rate'] = number_format($rate, 2, '.', '');
        }
    }

    return [
        'data' => $data,
        'errors' => $errors,
    ];
}

function create_event(array $data): int
{
    $statement = db()->prepare(
        'INSERT INTO events (event_date, event_text, thoughts, physical_effect, feeling_rate)
        VALUES (:event_date, :event_text, :thoughts, :physical_effect, :feeling_rate)'
    );

    $statement->execute([
        'event_date' => $data['event_date'],
        'event_text' => $data['event_text'],
        'thoughts' => $data['thoughts'],
        'physical_effect' => $data['physical_effect'],
        'feeling_rate' => $data['feeling_rate'],
    ]);

    return (int) db()->lastInsertId();
}

function validate_event_id($value): ?int
{
    $eventId = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
        ],
    ]);

    return $eventId === false ? null : (int) $eventId;
}

function event_exists(int $eventId): bool
{
    $statement = db()->prepare('SELECT 1 FROM events WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $eventId]);

    return $statement->fetchColumn() !== false;
}

function update_event(int $eventId, array $data): void
{
    $statement = db()->prepare(
        'UPDATE events
        SET
            event_date = :event_date,
            event_text = :event_text,
            thoughts = :thoughts,
            physical_effect = :physical_effect,
            feeling_rate = :feeling_rate
        WHERE id = :id'
    );

    $statement->execute([
        'id' => $eventId,
        'event_date' => $data['event_date'],
        'event_text' => $data['event_text'],
        'thoughts' => $data['thoughts'],
        'physical_effect' => $data['physical_effect'],
        'feeling_rate' => $data['feeling_rate'],
    ]);
}

function delete_event(int $eventId): bool
{
    $statement = db()->prepare('DELETE FROM events WHERE id = :id');
    $statement->execute(['id' => $eventId]);

    return $statement->rowCount() > 0;
}

function fetch_timeline_events(): array
{
    $statement = db()->query(
        "SELECT
            e.id,
            DATE_FORMAT(e.event_date, '%Y-%m-%d') AS event_date,
            e.event_text,
            e.thoughts,
            e.physical_effect,
            e.feeling_rate,
            e.created_at,
            e.updated_at,
            COALESCE(comment_stats.comment_count, 0) AS comment_count,
            COALESCE(comment_stats.unread_comment_count, 0) AS unread_comment_count
        FROM events e
        LEFT JOIN (
            SELECT
                event_id,
                COUNT(*) AS comment_count,
                SUM(CASE WHEN is_read_by_admin = 0 THEN 1 ELSE 0 END) AS unread_comment_count
            FROM comments
            GROUP BY event_id
        ) comment_stats ON comment_stats.event_id = e.id
        ORDER BY e.event_date DESC, e.created_at DESC, e.id DESC"
    );

    return array_map('normalize_timeline_event', $statement->fetchAll());
}

function normalize_timeline_event(array $event): array
{
    return [
        'id' => (int) $event['id'],
        'event_date' => (string) $event['event_date'],
        'event_text' => (string) $event['event_text'],
        'thoughts' => (string) $event['thoughts'],
        'physical_effect' => (string) $event['physical_effect'],
        'feeling_rate' => (float) $event['feeling_rate'],
        'created_at' => (string) $event['created_at'],
        'updated_at' => (string) $event['updated_at'],
        'comment_count' => (int) $event['comment_count'],
        'unread_comment_count' => (int) $event['unread_comment_count'],
    ];
}

function event_detail_payload(array $event): array
{
    return [
        'id' => (int) $event['id'],
        'eventDate' => (string) $event['event_date'],
        'eventDateLabel' => (string) ($event['event_date_label'] ?? $event['event_date']),
        'eventText' => (string) $event['event_text'],
        'thoughts' => (string) $event['thoughts'],
        'physicalEffect' => (string) $event['physical_effect'],
        'feelingRate' => format_feeling_rate((float) $event['feeling_rate']),
        'commentCount' => (int) $event['comment_count'],
        'unreadCommentCount' => (int) $event['unread_comment_count'],
    ];
}

function group_events_by_month_day(array $events): array
{
    $months = [];

    foreach ($events as $event) {
        $date = new DateTimeImmutable((string) $event['event_date']);
        $monthKey = $date->format('Y-m');
        $dayKey = $date->format('Y-m-d');

        if (!isset($months[$monthKey])) {
            $months[$monthKey] = [
                'key' => $monthKey,
                'label' => $date->format('F Y'),
                'event_count' => 0,
                'days' => [],
            ];
        }

        if (!isset($months[$monthKey]['days'][$dayKey])) {
            $months[$monthKey]['days'][$dayKey] = [
                'key' => $dayKey,
                'label' => $date->format('l, F j, Y'),
                'event_count' => 0,
                'events' => [],
            ];
        }

        $event['event_date_label'] = $date->format('F j, Y');
        $months[$monthKey]['days'][$dayKey]['events'][] = $event;
        $months[$monthKey]['days'][$dayKey]['event_count']++;
        $months[$monthKey]['event_count']++;
    }

    foreach ($months as $monthKey => $month) {
        $months[$monthKey]['days'] = array_values($month['days']);
    }

    return array_values($months);
}

function format_feeling_rate(float $rate): string
{
    return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
}
