<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Auth;

use App\Adapter\Http\Api\Auth\BearerCredential;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class AccessTokenAuthentication
 *
 * Establishes immutable authentication only on explicitly protected request pipelines
 */
final readonly class AccessTokenAuthentication implements MiddlewareInterface
{
    /**
     * Constructs AccessTokenAuthentication
     */
    public function __construct(private BearerCredential $credentials, private AuthenticateAccessToken $authentication)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = $this->authentication->authenticate($this->credentials->extract($request));

        return $handler->handle($request->withAttribute(AuthenticationContext::class, $context));
    }
}
