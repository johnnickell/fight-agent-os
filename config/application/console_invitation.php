<?php

declare(strict_types=1);

use App\Adapter\Console\AddUserRoleCommand;
use App\Adapter\Console\BootstrapSuperAdminCommand;
use App\Adapter\Console\BootstrapWorkspaceCommand;
use App\Adapter\Console\InviteUserCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\CredentialDelivery\RandomActivationCredentialGenerator;
use App\Application\Invitation\ConsoleAssignmentPolicy;
use App\Application\Invitation\ConsoleUserOperation;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\ActivationCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\Permission\QueryHandler\GetPermissionByIdHandler;
use Fight\AccessControl\Application\AccessControl\Role\QueryHandler\GetRoleByIdHandler;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\InvitePendingUserHandler;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(ActivationCredentialGenerator::class, static fn(): ActivationCredentialGenerator =>
        new RandomActivationCredentialGenerator());
    $container->set(InvitePendingUserHandler::class, static fn(Container $c): InvitePendingUserHandler =>
        new InvitePendingUserHandler(
            $c->get(UserRepository::class),
            $c->get(ActivationGrantRepository::class),
            $c->get(AuditEvidenceRepository::class),
            $c->get(TransactionalUnitOfWork::class),
            $c->get(ActivationCredentialGenerator::class),
            $c->get(InvitationDeliveryCipher::class),
            $c->get(Clock::class),
            $c->get(EventDispatcher::class)
        ));
    $container->set(ConsoleUserOperation::class, static fn(Container $c): ConsoleUserOperation =>
        new ConsoleUserOperation(
            $c->get(CommandBus::class),
            $c->get(QueryBus::class),
            $c->get(TransactionalUnitOfWork::class),
            $c->get(AuditEvidenceRepository::class),
            $c->get(ConsoleAssignmentPolicy::class)
        ));
    $container->set(ConsoleAssignmentPolicy::class, static fn(Container $c): ConsoleAssignmentPolicy =>
        new ConsoleAssignmentPolicy($c->get(QueryBus::class)));
    $container->get('messaging.command.router')->registerHandlers([
        InvitePendingUserHandler::commandRegistration() => InvitePendingUserHandler::class
    ]);
    $container->set(GetRoleByIdHandler::class, static fn(Container $c): GetRoleByIdHandler =>
        new GetRoleByIdHandler($c->get(RoleRepository::class)));
    $container->set(GetPermissionByIdHandler::class, static fn(Container $c): GetPermissionByIdHandler =>
        new GetPermissionByIdHandler($c->get(PermissionRepository::class)));
    $container->get('messaging.query.router')->registerHandlers([
        GetRoleByIdHandler::queryRegistration()       => GetRoleByIdHandler::class,
        GetPermissionByIdHandler::queryRegistration() => GetPermissionByIdHandler::class
    ]);
    $container->set(InviteUserCommand::class, static fn(Container $c): InviteUserCommand =>
        new InviteUserCommand($c->get(ConsoleUserOperation::class)));
    $container->set(AddUserRoleCommand::class, static fn(Container $c): AddUserRoleCommand =>
        new AddUserRoleCommand($c->get(ConsoleUserOperation::class)));
    $container->set(SafeConsoleApplication::class, static function (Container $c): SafeConsoleApplication {
        $application = new SafeConsoleApplication('Fight Agent OS');
        $application->addCommand($c->get(InviteUserCommand::class));
        $application->addCommand($c->get(AddUserRoleCommand::class));
        $application->addCommand($c->get(BootstrapSuperAdminCommand::class));
        $application->addCommand($c->get(BootstrapWorkspaceCommand::class));
        $application->setAutoExit(false);

        return $application;
    });
};
