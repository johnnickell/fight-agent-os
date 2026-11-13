<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\SessionResponseFailure;
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
        // The guard belongs only to this invocation, including when Common cannot return a response.
        // Observe the narrow transport obligation before Common catches it; never classify or report here.
        $guard = new class ($handler) implements RequestHandlerInterface {
            public bool $clearSessionCookies = false;

            /**
             * Constructs SessionResponseGuard
             */
            public function __construct(private readonly RequestHandlerInterface $handler)
            {
            }

            /**
             * @inheritDoc
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                try {
                    return $this->handler->handle($request);
                } catch (SessionResponseFailure $failure) {
                    $this->clearSessionCookies = true;
                    throw $failure;
                }
            }
        };
        $response = $this->safeErrors->process($request->withAttribute('correlation_id', $correlationId), $guard);
        if ($guard->clearSessionCookies) {
            $response = BrowserSessionCookies::clear($response);
        }

        return $response->withHeader('X-Correlation-ID', $correlationId)->withHeader('Cache-Control', 'no-store');
    }
}
