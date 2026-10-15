<?php

declare(strict_types=1);

use App\Adapter\Security\UnauthenticatedContextProvider;
use App\Application\Authorization\AuthenticatedRoleAssignment;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\InvitationCorrectionAuthorization;
use App\Application\Authorization\SessionAdministrationDecision;
use App\Application\Authorization\UnavailableEmailChangeAdministration;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationAdministrationAuthorization;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContextProvider;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\EmailChangeGrant\Service\EmailChangeAdministrationAuthorization;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionAdministrationAuthorization;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Auth\Hmac\HmacRequestService;
use Fight\Common\Adapter\Auth\Security\PhpPasswordHasher;
use Fight\Common\Adapter\Auth\Security\PhpPasswordValidator;
use Fight\Common\Application\Auth\RequestService;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Service\Container;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

return static function (Container $container): void {
    $requiredEnvironmentValue = static function (string $name): string {
        $value = getenv($name);

        if ($value === false || $value === '') {
            throw new RuntimeException(sprintf('%s must be configured.', $name));
        }

        return $value;
    };

    $container->set(AuthenticationContextProvider::class, static fn(): AuthenticationContextProvider =>
        new UnauthenticatedContextProvider());
    $container->set(AuthenticatedRoleAssignment::class, static fn(Container $c) =>
        new AuthenticatedRoleAssignment(
            $c->get(AuthenticationContextProvider::class),
            $c->get(AuthoritativePrincipalResolver::class),
            $c->get(RoleRepository::class),
            $c->get(CommandBus::class)
        ));
    $container->set(ValidatorInterface::class, static function (): ValidatorInterface {
        return Validation::createValidator();
    });
    $container->set(InstallationAuthority::class, static function (Container $container): InstallationAuthority {
        return new InstallationAuthority(
            $container->get(UserRepository::class),
            $container->get(RoleRepository::class),
            $container->get(PermissionRepository::class)
        );
    });
    $container->set(InvitationAdministrationAuthorization::class, static function (
        Container $container
    ): InvitationAdministrationAuthorization {
        return new InvitationCorrectionAuthorization($container->get(InstallationAuthority::class));
    });
    $container->set(SessionAdministrationAuthorization::class, static function (
        Container $container
    ): SessionAdministrationAuthorization {
        // No shared callback may authorize revocation with a read-only intent.
        return SessionAdministrationDecision::forSharedFallback($container->get(InstallationAuthority::class));
    });
    $container->set(
        EmailChangeAdministrationAuthorization::class,
        static fn(): EmailChangeAdministrationAuthorization => new UnavailableEmailChangeAdministration()
    );
    $container->set(PasswordHasher::class, static function (): PasswordHasher {
        return new PhpPasswordHasher(PASSWORD_ARGON2ID);
    });
    $container->set(PasswordValidator::class, static function (): PasswordValidator {
        return new PhpPasswordValidator(PASSWORD_ARGON2ID);
    });
    $container->set(
        RequestService::class,
        static function () use ($requiredEnvironmentValue): RequestService {
            return new HmacRequestService(
                $requiredEnvironmentValue('APP_HMAC_KEY'),
                $requiredEnvironmentValue('APP_HMAC_SECRET')
            );
        }
    );
};
