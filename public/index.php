<?php

declare(strict_types=1);

// Suppress displayed PHP errors before application boot, even in development.
ini_set('display_errors', '0');
// Keep credential-bearing request objects and transactional closures out of exception arguments.
// This is local to this HTTP process, not a host/global PHP configuration change.
ini_set('zend.exception_ignore_args', '1');
if (ini_get('zend.exception_ignore_args') !== '1') {
    http_response_code(503);
    header('Content-Type: text/plain');
    header('Cache-Control: no-store');
    echo 'Application unavailable.';

    return;
}

$app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__));
$app->run();
