<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Auth;

use App\Adapter\Http\Api\V1\Auth\AuthOperation;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Adapter\Security\HmacCsrfProofs;
use App\Application\Failure\PermissionDenied;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class PasswordChangeGuard
 *
 * Restricts the independently Bearer-authorized mutation to direct same-origin JSON and bounded admission
 */
final readonly class PasswordChangeGuard implements MiddlewareInterface
{
    /**
     * Constructs PasswordChangeGuard
     */
    public function __construct(private HmacCsrfProofs $origins, private AuthAdmission $admission)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $origin = $uri->getScheme().'://'.$uri->getHost().($uri->getPort() === null ? '' : ':'.$uri->getPort());
        $server = $request->getServerParams();
        $rawHost = $server['HTTP_HOST'] ?? $request->getHeaderLine('Host');
        foreach (array_keys($request->getHeaders()) as $name) {
            $unsafe = '/\A(?:forwarded|x-forwarded-.*|x-original-.*|x-method-override|x-http-method.*)\z/iD';
            if (preg_match($unsafe, $name)) {
                throw new PermissionDenied();
            }
        }
        foreach (
            [
                'Sec-Fetch-Site' => ['same-origin'],
                'Sec-Fetch-Mode' => ['cors', 'same-origin'],
                'Sec-Fetch-Dest' => ['empty']
            ] as $name => $accepted
        ) {
            $values = $request->getHeader($name);
            if ($values !== [] && (count($values) !== 1 || !in_array($values[0], $accepted, true))) {
                throw new PermissionDenied();
            }
        }
        if (
            $request->getMethod() !== 'POST' || $uri->getScheme() !== 'https'
            || !$this->origins->acceptsOrigin($origin) || !is_string($rawHost)
            || !$this->origins->acceptsOrigin('https://'.$rawHost)
            || count($request->getHeader('Host')) !== 1 || count($request->getHeader('Origin')) !== 1
            || !$this->origins->acceptsOrigin($request->getHeaderLine('Origin'))
            || $uri->getQuery() !== '' || $uri->getUserInfo() !== '' || $uri->getFragment() !== ''
            || count($request->getHeader('Content-Type')) !== 1
            || preg_match(
                '/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/iD',
                $request->getHeaderLine('Content-Type')
            ) !== 1
            || $request->hasHeader('Content-Encoding') || $request->hasHeader('Access-Control-Request-Method')
            || $request->hasHeader('Sec-Fetch-User')
        ) {
            throw new PermissionDenied();
        }
        $peer = $server['REMOTE_ADDR'] ?? null;
        $this->admission->endpoint(AuthOperation::PASSWORD_CHANGE, is_string($peer) ? $peer : '');

        return $handler->handle($request);
    }
}
