<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Auth;

use App\Adapter\Security\HmacCsrfProofs;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class CsrfBootstrapGuard
 *
 * Restricts the explicitly public bootstrap to the configured secure origin
 */
final readonly class CsrfBootstrapGuard implements MiddlewareInterface
{
    /**
     * Constructs CsrfBootstrapGuard
     */
    public function __construct(private HmacCsrfProofs $proofs, private JSendResponseFactory $responses)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $origin = $uri->getScheme().'://'.$uri->getHost().($uri->getPort() === null ? '' : ':'.$uri->getPort());
        $fetch = $request->getHeader('Sec-Fetch-Site');
        $submittedOrigin = $request->getHeader('Origin');

        if (
            $uri->getScheme() !== 'https' || $origin !== $this->proofs->origin()
            || $uri->getUserInfo() !== '' || $uri->getFragment() !== ''
            || count($fetch) > 1 || ($fetch !== [] && $fetch[0] !== 'same-origin')
            || count($submittedOrigin) > 1
            || ($submittedOrigin !== [] && $submittedOrigin[0] !== $this->proofs->origin())
            || $request->hasHeader('Access-Control-Request-Method')
        ) {
            return $this->reject(403);
        }

        if ($uri->getQuery() !== '') {
            return $this->reject(400);
        }

        return $handler->handle($request);
    }

    /**
     * Returns a sanitized non-cacheable JSend rejection
     */
    private function reject(int $status): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::error($status === 403 ? 'Forbidden.' : 'Bad request.'),
            $status,
            ['Cache-Control' => 'no-store']
        );
    }
}
