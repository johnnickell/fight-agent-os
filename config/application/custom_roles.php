<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Authority\CreateRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\RemoveRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\RenameRoleAction;
use App\Adapter\Http\Responder\Api\V1\Authority\RoleMutationResponder;
use App\Adapter\Persistence\Repository\PostgresRoleMutationAudit;
use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\ManageCustomRoles;
use App\Application\Authorization\RoleMutationUnitOfWork;
use App\Domain\Authorization\RoleMutationAudit;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Role\CommandHandler\CreateCustomRoleHandler;
use Fight\AccessControl\Application\AccessControl\Role\CommandHandler\RemoveCustomRoleHandler;
use Fight\AccessControl\Application\AccessControl\Role\CommandHandler\RenameCustomRoleHandler;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(RoleMutationAudit::class, static fn(Container $container) =>
        new PostgresRoleMutationAudit($container->get(Connection::class)));
    $container->set(RoleMutationUnitOfWork::class, static fn(Container $container) => new RoleMutationUnitOfWork(
        $container->get(TransactionalUnitOfWork::class),
        $container->get(RoleMutationAudit::class)
    ));
    foreach ([CreateCustomRoleHandler::class, RenameCustomRoleHandler::class] as $handler) {
        $container->set($handler, static fn(Container $container) => new $handler(
            $container->get(RoleRepository::class),
            $container->get(Clock::class),
            $container->get(RoleMutationUnitOfWork::class),
            $container->get(EventDispatcher::class)
        ));
        $container->get('messaging.command.router')->registerHandlers([$handler::commandRegistration() => $handler]);
    }
    $container->set(RemoveCustomRoleHandler::class, static fn(Container $container) => new RemoveCustomRoleHandler(
        $container->get(RoleRepository::class),
        $container->get(UserRepository::class),
        $container->get(Clock::class),
        $container->get(RoleMutationUnitOfWork::class),
        $container->get(EventDispatcher::class)
    ));
    $container->get('messaging.command.router')->registerHandlers([
        RemoveCustomRoleHandler::commandRegistration() => RemoveCustomRoleHandler::class
    ]);
    $container->set(ManageCustomRoles::class, static fn(Container $container) => new ManageCustomRoles(
        $container->get(AuthenticatedPermissions::class),
        $container->get(InstallationAuthority::class),
        $container->get(CommandBus::class),
        $container->get(QueryBus::class)
    ));
    $container->set(RoleMutationResponder::class, static fn(Container $container) =>
        new RoleMutationResponder($container->get(JSendResponseFactory::class)));
    foreach ([CreateRoleAction::class, RenameRoleAction::class, RemoveRoleAction::class] as $action) {
        $container->set($action, static fn(Container $container) => new $action(
            $container->get(ManageCustomRoles::class),
            $container->get(RoleMutationResponder::class)
        ));
    }
};
