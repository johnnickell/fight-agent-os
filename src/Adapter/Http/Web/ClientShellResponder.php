<?php

declare(strict_types=1);

namespace App\Adapter\Http\Web;

use Fight\Common\Application\Templating\TemplateEngine;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Class ClientShellResponder
 *
 * Presents the public foundation without environment or authority hydration
 */
final class ClientShellResponder
{
    /**
     * Constructs ClientShellResponder
     */
    public function __construct(
        private readonly TemplateEngine $templates,
        private readonly PublicRuntimeConfiguration $configuration = new PublicRuntimeConfiguration()
    ) {
    }

    /**
     * Renders the shell or a safe deployment failure
     *
     * @param ResponseInterface $response
     * @param array{prepaint: string, script: string, stylesheet: string}|null $assets
     */
    public function respond(ResponseInterface $response, ?array $assets): ResponseInterface
    {
        $body = null;
        $status = 503;
        $contentType = 'text/html; charset=utf-8';
        if ($assets !== null) {
            try {
                $body = $this->templates->render('@client/shell.html.twig', [
                    'assets' => $assets,
                    // Fixed ADR 0004 allowlist, never an environment/configuration-object dump.
                    'config' => $this->configuration->toArray()
                ]);
                $status = 200;
            } catch (Throwable) {
                // A partial render must never escape into the public document.
            }
        }

        if ($body === null) {
            try {
                $body = $this->templates->render('@client/unavailable.html.twig');
            } catch (Throwable) {
                $body = 'Application unavailable. Please contact the installation operator.';
                $contentType = 'text/plain; charset=utf-8';
            }
        }
        $response->getBody()->write($body);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader(
                'Content-Security-Policy',
                implode(' ', [
                    "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:;",
                    "font-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"
                ])
            );
    }
}
