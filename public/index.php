<?php

declare(strict_types=1);

// Suppress displayed PHP errors before application boot, even in development.
ini_set('display_errors', '0');

$app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__));
$app->run();
