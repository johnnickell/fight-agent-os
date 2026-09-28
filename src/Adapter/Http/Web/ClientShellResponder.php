<?php

declare(strict_types=1);

namespace App\Adapter\Http\Web;

use Psr\Http\Message\ResponseInterface;

/**
 * Class ClientShellResponder
 *
 * Presents the public foundation without environment or authority hydration
 */
final class ClientShellResponder
{
    /**
     * Renders the shell or a safe deployment failure
     *
     * @param ResponseInterface $response
     * @param array{script: string, stylesheet: string}|null $assets
     */
    public function respond(ResponseInterface $response, ?array $assets): ResponseInterface
    {
        $head = '';
        $body = '<main><h1>Application unavailable</h1><p>Please contact the installation operator.</p></main>';
        $title = 'Application unavailable';
        if ($assets !== null) {
            $script = htmlspecialchars($assets['script'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $stylesheet = htmlspecialchars($assets['stylesheet'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $head = '<link rel="stylesheet" href="'.$stylesheet.'">';
            $head .= '<script type="module" src="'.$script.'"></script>';
            // Fixed ADR 0004 allowlist, never an environment/configuration-object dump.
            $config = '{"schema_version":1,"api_base_path":"/api/v1"}';
            $body = <<<HTML
                <script id="runtime-config" type="application/json">{$config}</script>
                <div id="app"><main><h1>Loading application</h1></main></div>
                <noscript><p>JavaScript is required for the application foundation.</p></noscript>
                HTML;
            $title = 'Application foundation';
        }

        $response->getBody()->write(<<<HTML
            <!doctype html><html lang="en" data-bs-theme="light"><head><meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$title} — Fight Agent OS</title>{$head}</head><body>{$body}</body></html>
            HTML);

        return $response
            ->withStatus($assets === null ? 503 : 200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader(
                'Content-Security-Policy',
                implode(' ', [
                    "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:;",
                    "connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"
                ])
            );
    }
}
