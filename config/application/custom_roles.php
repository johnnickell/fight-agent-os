<?php

declare(strict_types=1);

use App\Adapter\Authorization\InstallationPermissionAdmission;
use App\Adapter\Http\Action\Api\V1\Authority\CreateRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\GrantRolePermissionAction;
use App\Adapter\Http\Action\Api\V1\Authority\RemoveRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\RenameRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\RevokeRolePermissionAction;
use App\Adapter\Http\Responder\Api\V1\Authority\RoleMutationResponder;
use Fight\AccessControl\Application\AccessControl\Role\CommandHandler\CreateCustomRoleHandler;
use Fight\AccessControl\Application\AccessControl\Role\CommandHandler\GrantPermissionToCustomRoleHandler;
use Fight\AccessControl\Application\AccessControl\Role\CommandHandler\RemoveCustomRoleHandler;
use Fight\AccessControl\Application\AccessControl\Role\CommandHandler\RenameCustomRoleHandler;
use Fight\AccessControl\Application\AccessControl\Role\CommandHandler\RevokePermissionFromCustomRoleHandler;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    foreach ([CreateCustomRoleHandler::class, RenameCustomRoleHandler::class] as $handler) {
        $container->set($handler, static fn(Container $container) => new $handler(
            $container->get(RoleRepository::class),
            $container->get(Clock::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(EventDispatcher::class),
            $container->get(AuditEvidenceRepository::class)
        ));
        $container->get('messaging.command.router')->registerHandlers([$handler::commandRegistration() => $handler]);
    }
    $container->set(RemoveCustomRoleHandler::class, static fn(Container $container) => new RemoveCustomRoleHandler(
        $container->get(RoleRepository::class),
        $container->get(UserRepository::class),
        $container->get(Clock::class),
        $container->get(TransactionalUnitOfWork::class),
        $container->get(EventDispatcher::class),
        $container->get(AuditEvidenceRepository::class)
    ));
    $container->get('messaging.command.router')->registerHandlers([
        RemoveCustomRoleHandler::commandRegistration() => RemoveCustomRoleHandler::class
    ]);
    foreach ([GrantPermissionToCustomRoleHandler::class, RevokePermissionFromCustomRoleHandler::class] as $handler) {
        $container->set($handler, static fn(Container $container) => new $handler(
            $container->get(RoleRepository::class),
            $container->get(PermissionRepository::class),
            $container->get(Clock::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(EventDispatcher::class),
            $container->get(AuditEvidenceRepository::class)
        ));
        $container->get('messaging.command.router')->registerHandlers([$handler::commandRegistration() => $handler]);
    }
    $container->set(RoleMutationResponder::class, static fn(Container $container) =>
        new RoleMutationResponder($container->get(JSendResponseFactory::class)));
    foreach ([CreateRoleAction::class, RemoveRoleAction::class, GrantRolePermissionAction::class, RevokeRolePermissionAction::class] as $action) {
        $container->set($action, static fn(Container $container) => new $action(
            $container->get(CommandBus::class),
            $container->get(InstallationPermissionAdmission::class),
            $container->get(RoleMutationResponder::class)
        ));
    }
    $container->set(RenameRoleAction::class, static fn(Container $container) => new RenameRoleAction(
        $container->get(CommandBus::class),
        $container->get(QueryBus::class),
        $container->get(InstallationPermissionAdmission::class),
        $container->get(RoleMutationResponder::class)
    ));
};
