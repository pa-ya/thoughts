<?php

declare(strict_types=1);

require_once __DIR__ . '/../thoughts-api/src/auth.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

sign_out();
redirect_to('index.php');
