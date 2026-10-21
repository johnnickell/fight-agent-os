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
    $container->set(RoleMutationAudit::class, static fn(Container $c) =>
        new PostgresRoleMutationAudit($c->get(Connection::class)));
    $container->set(RoleMutationUnitOfWork::class, static fn(Container $c) => new RoleMutationUnitOfWork(
        $c->get(TransactionalUnitOfWork::class),
        $c->get(RoleMutationAudit::class)
    ));
    foreach ([CreateCustomRoleHandler::class, RenameCustomRoleHandler::class] as $handler) {
        $container->set($handler, static fn(Container $c) => new $handler(
            $c->get(RoleRepository::class),
            $c->get(Clock::class),
            $c->get(RoleMutationUnitOfWork::class),
            $c->get(EventDispatcher::class)
        ));
        $container->get('messaging.command.router')->registerHandlers([$handler::commandRegistration() => $handler]);
    }
    $container->set(RemoveCustomRoleHandler::class, static fn(Container $c) => new RemoveCustomRoleHandler(
        $c->get(RoleRepository::class),
        $c->get(UserRepository::class),
        $c->get(Clock::class),
        $c->get(RoleMutationUnitOfWork::class),
        $c->get(EventDispatcher::class)
    ));
    $container->get('messaging.command.router')->registerHandlers([
        RemoveCustomRoleHandler::commandRegistration() => RemoveCustomRoleHandler::class
    ]);
    $container->set(ManageCustomRoles::class, static fn(Container $c) => new ManageCustomRoles(
        $c->get(AuthenticatedPermissions::class),
        $c->get(InstallationAuthority::class),
        $c->get(CommandBus::class),
        $c->get(QueryBus::class)
    ));
    $container->set(RoleMutationResponder::class, static fn(Container $c) =>
        new RoleMutationResponder($c->get(JSendResponseFactory::class)));
    foreach ([CreateRoleAction::class, RenameRoleAction::class, RemoveRoleAction::class] as $action) {
        $container->set($action, static fn(Container $c) => new $action(
            $c->get(ManageCustomRoles::class),
            $c->get(RoleMutationResponder::class)
        ));
    }
};
