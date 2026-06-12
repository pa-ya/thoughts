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
