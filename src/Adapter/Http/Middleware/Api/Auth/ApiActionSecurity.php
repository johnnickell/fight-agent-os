<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Auth;

use App\Adapter\Http\Api\Auth\ActionAccess;
use App\Adapter\Http\Api\Auth\BearerCredential;
use App\Adapter\Http\Api\Auth\RequestAuthenticationContext;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class ApiActionSecurity
 *
 * Guards the selected API Action before input validation, construction and dispatch
 */
final readonly class ApiActionSecurity implements MiddlewareInterface
{
    /**
     * Constructs ApiActionSecurity
     */
    public function __construct(
        private BearerCredential $credentials,
        private AuthenticateAccessToken $authentication,
        private AuthoritativePrincipalResolver $principals,
        private PermissionRepository $permissions,
        private PermissionGuard $guard
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $access = ActionAccess::inspect(RouteContext::fromRequest($request)->getRoute()?->getCallable());
        if ($access->public) {
            return $handler->handle($request);
        }
        // Invalid or unavailable catalog metadata is a configuration failure, never an authority grant.
        foreach ($access->permissions as $name) {
            $permission = $this->permissions->getByName($name);
            if ($permission === null || !$permission->getName()->equals($name)) {
                throw new LogicException('Unavailable API Action permission declaration.');
            }
        }
        try {
            $authentication = $this->authentication->authenticate($this->credentials->extract($request));
            // Never store this caching provider in the shared container or reuse it between requests.
            $provider = new CurrentPrincipalProvider(
                new RequestAuthenticationContext($authentication),
                $this->principals
            );
            $context = new SecurityContext($provider->getCurrentPrincipal());
        } catch (Throwable) {
            throw new AuthenticationRequired();
        }
        $this->guard->assertAllowed($context, $access->permissions);

        return $handler->handle($request
            ->withAttribute(AuthenticationContext::class, $authentication)
            ->withAttribute(CurrentPrincipalProvider::class, $provider)
            ->withAttribute(SecurityContext::class, $context));
    }
}
