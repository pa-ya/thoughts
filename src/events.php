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

