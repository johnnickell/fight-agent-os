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
        $this->assertRequest($request);

        return $handler->handle($request);
    }

    /**
     * Rejects unsafe transport before any authentication or admission dispatch
     */
    public function assertRequest(ServerRequestInterface $request): void
    {
        $uri = $request->getUri();
        $origin = $uri->getScheme().'://'.$uri->getHost().($uri->getPort() === null ? '' : ':'.$uri->getPort());
        $host = $request->getHeaderLine('Host');
        $hostAccepted = $this->proofs->acceptsOrigin('https://'.$host);
        $server = $request->getServerParams();
        if (array_key_exists('HTTP_HOST', $server)) {
            // Slim PSR-7 reconstructs Host from URI::getHost(), dropping a nondefault port.
            // Qualify the original ingress authority independently; never infer a missing port.
            $rawHost = $server['HTTP_HOST'];
            if (!is_string($rawHost) || !$this->proofs->acceptsOrigin('https://'.$rawHost)) {
                throw new PermissionDenied();
            }
            $hostAccepted = $hostAccepted || $host === $uri->getHost();
        }
        $fetch = $request->getHeader('Sec-Fetch-Site');
        $proof = $request->getHeader('X-CSRF-Proof');
        $cookies = $request->getHeader('Cookie');
        $rawCookies = $cookies[0] ?? '';
        $count = preg_match_all('/(?:\A|;)\s*'.preg_quote(CsrfCookie::NAME, '/').'=([^;]*)/', $rawCookies, $matches);
        $nonce = $matches[1][0] ?? '';
        foreach (array_keys($request->getHeaders()) as $name) {
            $unsafe = '/\A(?:forwarded|x-forwarded-.*|x-original-.*|x-method-override|x-http-method)\z/iD';
            if (preg_match($unsafe, $name)) {
                throw new PermissionDenied();
            }
        }
        foreach (['Sec-Fetch-Mode' => ['cors', 'same-origin'], 'Sec-Fetch-Dest' => ['empty']] as $name => $values) {
            $header = $request->getHeader($name);
            if ($header !== [] && (count($header) !== 1 || !in_array($header[0], $values, true))) {
                throw new PermissionDenied();
            }
        }
        if (
            $request->getMethod() !== 'POST' || !$this->proofs->acceptsOrigin($origin)
            || $uri->getScheme() !== 'https' || $uri->getUserInfo() !== '' || $uri->getFragment() !== ''
            || count($request->getHeader('Host')) !== 1
            || !$hostAccepted
            || count($request->getHeader('Origin')) !== 1
            || !$this->proofs->acceptsOrigin($request->getHeaderLine('Origin'))
            || count($fetch) > 1 || ($fetch !== [] && $fetch !== ['same-origin'])
            || count($proof) !== 1 || count($cookies) !== 1 || strlen($rawCookies) > 4096 || $count !== 1
            || !$this->proofs->verify($nonce, $proof[0], $this->clock->now())
            || $request->hasHeader('Authorization') || $request->hasHeader('X-HTTP-Method-Override')
            || $request->hasHeader('Access-Control-Request-Method') || $request->hasHeader('Sec-Fetch-User')
            || $uri->getQuery() !== '' || count($request->getHeader('Content-Type')) !== 1
            || preg_match(
                '/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/iD',
                $request->getHeaderLine('Content-Type')
            ) !== 1
            || $request->hasHeader('Content-Encoding')
        ) {
            throw new PermissionDenied();
        }
    }
}
