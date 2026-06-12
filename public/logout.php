<?php

declare(strict_types=1);

require_once __DIR__ . '/../thoughts-api/src/auth.php';

sign_out();
redirect_to('index.php');
