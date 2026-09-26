<?php

declare(strict_types=1);

use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\Repository\PostgresActivationGrantRepository;
use App\Adapter\Persistence\Repository\PostgresPermissionRepository;
use App\Adapter\Persistence\Repository\PostgresPasswordResetGrantRepository;
use App\Adapter\Persistence\Repository\PostgresRefreshSessionRepository;
use App\Adapter\Persistence\Repository\PostgresRoleRepository;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(Connection::class, static function (Container $container): Connection {
        return DriverManager::getConnection((new DsnParser([
            'postgres' => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql',
        ]))->parse($container['app.database_url']));
    });
    $container->set(EntityManagerInterface::class, static function (Container $container): EntityManagerInterface {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], getenv('APP_ENV') !== 'production');
        $configuration->enableNativeLazyObjects(true);

        return new EntityManager($container->get(Connection::class), $configuration);
    });
    $container->set(AuthorizationReferenceFences::class, static function (Container $container): AuthorizationReferenceFences {
        return new AuthorizationReferenceFences($container->get(Connection::class));
    });
    $container->set(AuthenticationAuthorityFences::class, static function (Container $container): AuthenticationAuthorityFences {
        return new AuthenticationAuthorityFences($container->get(Connection::class));
    });
    $container->set(PermissionRepository::class, static function (Container $container): PermissionRepository {
        return new PostgresPermissionRepository(
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
        return new PostgresRefreshSessionRepository($container->get(Connection::class));
    });
    $container->set(PasswordResetGrantRepository::class, static function (Container $container): PasswordResetGrantRepository {
        return new PostgresPasswordResetGrantRepository($container->get(Connection::class));
    });
    $container->set(ActivationGrantRepository::class, static function (Container $container): ActivationGrantRepository {
        return new PostgresActivationGrantRepository($container->get(Connection::class));
    });
    $container->set(TransactionalUnitOfWork::class, static function (Container $container): TransactionalUnitOfWork {
        return new DoctrineTransactionalUnitOfWork($container->get(EntityManagerInterface::class));
    });
};
