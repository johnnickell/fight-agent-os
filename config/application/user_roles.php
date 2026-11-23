<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Users\AssignUserRoleAction;
use App\Adapter\Http\Action\Api\V1\Users\RemoveUserRoleAction;
use App\Adapter\Http\Responder\Api\V1\Users\UserLifecycleResponder;
use App\Application\Authorization\PermissionGuard;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\RemoveRoleFromUserHandler;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(AssignUserRoleAction::class, static fn(Container $container) => new AssignUserRoleAction(
        $container->get(QueryBus::class),
        $container->get(CommandBus::class),
        $container->get(PermissionGuard::class),
        $container->get(UserLifecycleResponder::class)
    ));
    $container->set(RemoveUserRoleAction::class, static fn(Container $container) => new RemoveUserRoleAction(
        $container->get(QueryBus::class),
        $container->get(CommandBus::class),
        $container->get(UserLifecycleResponder::class)
    ));
    $container->set(RemoveRoleFromUserHandler::class, static fn(Container $container) => new RemoveRoleFromUserHandler(
        $container->get(UserRepository::class),
        $container->get(RoleRepository::class),
        $container->get(Clock::class),
        $container->get(TransactionalUnitOfWork::class),
        $container->get(EventDispatcher::class),
        $container->get(AuditEvidenceRepository::class)
    ));
    $container->get('messaging.command.router')->registerHandlers([
        RemoveRoleFromUserHandler::commandRegistration() => RemoveRoleFromUserHandler::class
    ]);
};
