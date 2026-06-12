<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

sign_out();
redirect_to('index.php');
