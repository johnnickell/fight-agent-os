<?php

declare(strict_types=1);

// Development HTTP router: mirror the static allowlist and retain dotted shell routes.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (
    is_string($path)
    && (
        $path === '/favicon.svg'
        || preg_match('~\A/build/(?:main|chunk|prepaint)-[A-Z0-9]{8}\.(?:js|css)(?:\.LEGAL\.txt)?\z~', $path) === 1
    )
    && is_file(dirname(__DIR__).'/public'.$path)
) {
    return false;
}

if (is_string($path) && str_starts_with($path, '/build/')) {
    http_response_code(404);
    header('X-Content-Type-Options: nosniff');

    return true;
}

require dirname(__DIR__).'/public/index.php';
