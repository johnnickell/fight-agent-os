<?php

declare(strict_types=1);

// Development HTTP router: mirror the static allowlist and retain dotted shell routes.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/favicon.svg' && is_file(dirname(__DIR__).'/public/favicon.svg')) {
    return false;
}

$fontName = '(?:latin|greek|cyrillic)(?:-ext)?-(?:400|500|600|700)-normal-[A-Z0-9]{8}\.woff2';
if (is_string($path) && str_starts_with($path, '/build/')) {
    $allowed = preg_match('~\A/build/(?:main|chunk|prepaint)-[A-Z0-9]{8}\.(?:js|css)(?:\.LEGAL\.txt)?\z~', $path) === 1
        || preg_match('~\A/build/(?:ubuntu|open-sans)-(?:'.$fontName.'|[A-Z0-9]{8}\.LEGAL\.txt)\z~', $path) === 1;
    $file = dirname(__DIR__).'/var/client'.$path;
    if ($allowed && is_file($file)) {
        $contentType = match (true) {
            str_ends_with($path, '.woff2') => 'font/woff2',
            str_ends_with($path, '.txt') => 'text/plain; charset=utf-8',
            str_ends_with($path, '.css') => 'text/css; charset=utf-8',
            default => 'text/javascript; charset=utf-8'
        };
        header('Content-Type: '.$contentType);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Content-Type-Options: nosniff');
        readfile($file);

        return true;
    }
    http_response_code(404);
    header('X-Content-Type-Options: nosniff');

    return true;
}

require dirname(__DIR__).'/public/index.php';
