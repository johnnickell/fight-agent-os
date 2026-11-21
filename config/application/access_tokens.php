<?php

declare(strict_types=1);

use App\Adapter\Http\Api\Auth\BearerCredential;
use App\Adapter\Http\Middleware\Api\Auth\ApiActionSecurity;
use App\Adapter\Security\AccessToken\AccessJwt;
use App\Adapter\Security\AccessToken\AccessKeyring;
use App\Adapter\Security\AccessToken\AuthenticateAccessToken;
use App\Adapter\Security\AccessToken\SystemAccessClock;
use App\Application\Authorization\PermissionGuard;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Auth\Security\TokenDecoder;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(AccessKeyring::class, static function (Container $container): AccessKeyring {
        $configuration = getenv('APP_ACCESS_TOKEN_CONFIG');

        return new AccessKeyring(
            $configuration === false ? '' : $configuration,
            $container->get(SystemAccessClock::class)
        );
    });
    $container->set(SystemAccessClock::class, static fn(): SystemAccessClock => new SystemAccessClock());
    $container->set(AccessJwt::class, static fn(Container $container): AccessJwt => new AccessJwt(
        $container->get(AccessKeyring::class),
        $container->get(SystemAccessClock::class)
    ));
    $container->set(
        TokenEncoder::class,
        static fn(Container $container): TokenEncoder => $container->get(AccessJwt::class)
    );
    $container->set(
        TokenDecoder::class,
        static fn(Container $container): TokenDecoder => $container->get(AccessJwt::class)
    );
    $container->set(
        AuthenticateAccessToken::class,
        static fn(Container $container): AuthenticateAccessToken => new AuthenticateAccessToken(
            $container->get(TokenDecoder::class),
            $container->get(UserRepository::class),
            $container->get(RefreshSessionRepository::class),
            $container->get(SystemAccessClock::class)
        )
    );
    $container->set(BearerCredential::class, static fn(): BearerCredential => new BearerCredential());

    $container->set(AuthoritativePrincipalResolver::class, static fn(
        Container $container
    ): AuthoritativePrincipalResolver => new AuthoritativePrincipalResolver(
        $container->get(UserRepository::class),
        $container->get(RefreshSessionRepository::class),
        $container->get(RoleRepository::class),
        $container->get(PermissionRepository::class),
        $container->get(SystemAccessClock::class)
    ));
    $container->set(ApiActionSecurity::class, static fn(
        Container $container
    ): ApiActionSecurity => new ApiActionSecurity(
        $container->get(BearerCredential::class),
        $container->get(AuthenticateAccessToken::class),
        $container->get(AuthoritativePrincipalResolver::class),
        $container->get(PermissionRepository::class),
        $container->get(PermissionGuard::class)
    ));

    // Fail at boot in every environment; public routes never bypass invalid signing configuration.
    $container->get(AccessKeyring::class);
};
