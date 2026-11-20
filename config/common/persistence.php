<?php

declare(strict_types=1);

use App\Adapter\Persistence\AccessControl\Agent\PostgresAgentRepository;
use App\Adapter\Persistence\AccessControl\Feature\PostgresFeatureRepository;
use App\Adapter\Persistence\AccessControl\Permission\PostgresPermissionRepository;
use App\Adapter\Persistence\AccessControl\Role\PostgresRoleRepository;
use App\Adapter\Persistence\AccessControl\User\PostgresUserRepository;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\Repository\PostgresActivationGrantRepository;
use App\Adapter\Persistence\Repository\PostgresAuditEvidenceRepository;
use App\Adapter\Persistence\Repository\PostgresEmailChangeGrantRepository;
use App\Adapter\Persistence\Repository\PostgresPasswordResetGrantRepository;
use App\Adapter\Persistence\Repository\PostgresRefreshSessionRepository;
use App\Domain\Authorization\MembershipEvidenceRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentRepository;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(Connection::class, static function (Container $container): Connection {
        return DriverManager::getConnection(new DsnParser([
            'postgres'   => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql'
        ])->parse($container['app.database_url']));
    });
    $container->set(AuthorizationReferenceFences::class, static function (
        Container $container
    ): AuthorizationReferenceFences {
        return new AuthorizationReferenceFences($container->get(Connection::class));
    });
    $container->set(AuthenticationAuthorityFences::class, static function (
        Container $container
    ): AuthenticationAuthorityFences {
        return new AuthenticationAuthorityFences($container->get(Connection::class));
    });
    $container->set(PermissionRepository::class, static function (Container $container): PermissionRepository {
        return new PostgresPermissionRepository(
            $container->get(Connection::class),
            $container->get(AuthorizationReferenceFences::class)
        );
    });
    $container->set(AgentRepository::class, static function (Container $container): AgentRepository {
        return new PostgresAgentRepository(
            $container->get(Connection::class),
            $container->get(AuthorizationReferenceFences::class)
        );
    });
    $container->set(FeatureRepository::class, static function (Container $container): FeatureRepository {
        return new PostgresFeatureRepository(
            $container->get(Connection::class),
            $container->get(AuthorizationReferenceFences::class)
        );
    });
    $container->set(RoleRepository::class, static function (Container $container): RoleRepository {
        return new PostgresRoleRepository(
            $container->get(Connection::class),
            $container->get(AuthorizationReferenceFences::class)
        );
    });
    $container->set(UserRepository::class, static function (Container $container): UserRepository {
        return new PostgresUserRepository(
            $container->get(Connection::class),
            $container->get(AuthorizationReferenceFences::class),
            $container->get(AuthenticationAuthorityFences::class)
        );
    });
    $container->set(RefreshSessionRepository::class, static function (Container $container): RefreshSessionRepository {
        return new PostgresRefreshSessionRepository(
            $container->get(Connection::class),
            $container->get(AuthenticationAuthorityFences::class)
        );
    });
    $container->set(PasswordResetGrantRepository::class, static function (
        Container $container
    ): PasswordResetGrantRepository {
        return new PostgresPasswordResetGrantRepository($container->get(Connection::class));
    });
    $container->set(ActivationGrantRepository::class, static function (
        Container $container
    ): ActivationGrantRepository {
        return new PostgresActivationGrantRepository($container->get(Connection::class));
    });
    $container->set(EmailChangeGrantRepository::class, static function (
        Container $container
    ): EmailChangeGrantRepository {
        return new PostgresEmailChangeGrantRepository($container->get(Connection::class));
    });
    $container->set(PostgresAuditEvidenceRepository::class, static fn(Container $container) =>
        new PostgresAuditEvidenceRepository($container->get(Connection::class)));
    $container->set(AuditEvidenceRepository::class, static fn(Container $container): AuditEvidenceRepository =>
        $container->get(PostgresAuditEvidenceRepository::class));
    $container->set(MembershipEvidenceRepository::class, static fn(Container $container) =>
        $container->get(PostgresAuditEvidenceRepository::class));
    $container->set(TransactionalUnitOfWork::class, static function (
        Container $container
    ): TransactionalUnitOfWork {
        return new DbalTransactionalUnitOfWork($container->get(Connection::class));
    });
};
