<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Auth;

use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Security\HmacCsrfProofs;
use App\Application\Failure\PermissionDenied;
use App\Application\Security\Csrf\Clock\CsrfClock;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class BrowserMutationGuard
 *
 * Applies the exact secure-origin and nonce-bound CSRF mutation profile
 */
final readonly class BrowserMutationGuard implements MiddlewareInterface
{
    /**
     * Constructs BrowserMutationGuard
     */
    public function __construct(private HmacCsrfProofs $proofs, private CsrfClock $clock)
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
        $proof = $request->getHeader('X-CSRF-Proof');
        $cookies = $request->getHeader('Cookie');
        $rawCookies = $cookies[0] ?? '';
        $count = preg_match_all('/(?:\A|;)\s*'.preg_quote(CsrfCookie::NAME, '/').'=([^;]*)/', $rawCookies, $matches);
        $nonce = $matches[1][0] ?? '';
        if (
            $request->getMethod() !== 'POST' || $origin !== $this->proofs->origin()
            || $uri->getScheme() !== 'https' || $uri->getUserInfo() !== '' || $uri->getFragment() !== ''
            || $request->getHeader('Origin') !== [$this->proofs->origin()]
            || count($fetch) > 1 || ($fetch !== [] && $fetch !== ['same-origin'])
            || count($proof) !== 1 || count($cookies) !== 1 || strlen($rawCookies) > 4096 || $count !== 1
            || !$this->proofs->verify($nonce, $proof[0], $this->clock->now())
            || $request->hasHeader('Authorization') || $request->hasHeader('X-HTTP-Method-Override')
            || $request->hasHeader('Access-Control-Request-Method')
        ) {
            throw new PermissionDenied();
        }

        return $handler->handle($request);
    }
}
