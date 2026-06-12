<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

const COMMENT_TEXT_MAX_LENGTH = 1024;

function comment_field_max_length(): int
{
    return COMMENT_TEXT_MAX_LENGTH;
}

