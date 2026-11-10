<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class SecurityHeaders
 *
 * Applies the same restrictive browser policy to successes and contained failures
 */
final readonly class SecurityHeaders implements MiddlewareInterface
{
    public const string CSP = self::SOURCES.self::RESOURCES."form-action 'none'; frame-ancestors 'none'";

    private const string SOURCES = "default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; ";
    private const string RESOURCES = "img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'none'; ";

    /**
     * Constructs SecurityHeaders
     */
    public function __construct(private bool $production)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        foreach (array_keys($response->getHeaders()) as $name) {
            if (str_starts_with(strtolower($name), 'access-control-')) {
                $response = $response->withoutHeader($name);
            }
        }
        $headers = [
            'Content-Security-Policy'      => self::CSP,
            'X-Content-Type-Options'       => 'nosniff',
            'X-Frame-Options'              => 'DENY',
            'Referrer-Policy'              => 'no-referrer',
            'Permissions-Policy'           => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy'   => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Cache-Control'                => 'no-store'
        ];
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        // Never persist HSTS on local development names or an insecure response.
        $host = strtolower($request->getUri()->getHost());
        if (
            $this->production && $request->getUri()->getScheme() === 'https'
            && $host !== 'localhost' && !str_ends_with($host, '.localhost')
        ) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000');
        } else {
            $response = $response->withoutHeader('Strict-Transport-Security');
        }

        return $response;
    }
}
