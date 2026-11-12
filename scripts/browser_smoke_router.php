<?php

declare(strict_types=1);

// Test-only TLS fixture entry. Only the loopback HTTPS terminator can reach this
// server; never deploy it or accept forwarded authority from a request header.
header('X-Browser-Smoke-Fixture: '.getenv('BROWSER_SMOKE_FIXTURE'));
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '19443';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'localhost:19443';
require __DIR__.'/serve.php';
