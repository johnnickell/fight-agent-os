<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api;

use Fight\Common\Adapter\Middleware\Psr15\SafeJSendErrorMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ApiResponseBoundary
 *
 * Scopes Common's sole error boundary and independently correlates all API responses
 */
final readonly class ApiResponseBoundary implements MiddlewareInterface
{
    /**
     * Constructs ApiResponseBoundary
     */
    public function __construct(private SafeJSendErrorMiddleware $safeErrors)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path !== '/api' && !str_starts_with($path, '/api/')) {
            return $handler->handle($request);
        }

        $inbound = $request->getHeader('X-Correlation-ID');
        $accepted = count($inbound) === 1 && preg_match('/\A[a-f0-9]{32}\z/D', $inbound[0]) === 1;
        $correlationId = $accepted ? $inbound[0] : bin2hex(random_bytes(16));
        $response = $this->safeErrors->process($request->withAttribute('correlation_id', $correlationId), $handler);

        return $response->withHeader('X-Correlation-ID', $correlationId)->withHeader('Cache-Control', 'no-store');
    }
}
