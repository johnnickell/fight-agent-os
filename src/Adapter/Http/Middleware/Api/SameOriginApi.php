<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api;

use App\Adapter\Security\HmacCsrfProofs;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class SameOriginApi
 *
 * Denies cross-origin API delivery without granting authority to absent Origin headers
 */
final readonly class SameOriginApi implements MiddlewareInterface
{
    /**
     * Constructs SameOriginApi
     */
    public function __construct(private HmacCsrfProofs $proofs)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path === '/api' || str_starts_with($path, '/api/')) {
            $origins = $request->getHeader('Origin');
            if (
                count($origins) > 1
                || ($origins !== [] && !$this->proofs->acceptsOrigin($origins[0]))
                || $request->hasHeader('Access-Control-Request-Method')
                || $request->hasHeader('Access-Control-Request-Headers')
                || in_array($request->getHeaderLine('Sec-Fetch-Site'), ['cross-site', 'same-site'], true)
            ) {
                throw new PermissionDenied();
            }
        }

        return $handler->handle($request);
    }
}
