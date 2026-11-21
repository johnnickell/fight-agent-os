<?php

declare(strict_types=1);

use App\Adapter\Console\AddUserRoleCommand;
use App\Adapter\Console\BootstrapSuperAdminCommand;
use App\Adapter\Console\BootstrapWorkspaceCommand;
use App\Adapter\Console\InviteUserCommand;
use App\Adapter\Console\PruneSessionPresentationCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\CredentialDelivery\RandomActivationCredentialGenerator;
use App\Adapter\Persistence\Authorization\PostgresMembershipProgress;
use App\Application\Authorization\CommandHandler\AddUserRoleHandler;
use App\Application\Authorization\CommandHandler\InviteOrdinaryUserHandler;
use App\Application\Authorization\InstallationAssignmentPolicy;
use App\Domain\Authorization\ManagedPolicyCatalog;
use App\Domain\Authorization\MembershipEvidenceRepository;
use App\Domain\Authorization\MembershipProgress;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
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
    $container->set(InvitePendingUserHandler::class, static fn(Container $container): InvitePendingUserHandler =>
        new InvitePendingUserHandler(
            $container->get(UserRepository::class),
            $container->get(ActivationGrantRepository::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(ActivationCredentialGenerator::class),
            $container->get(InvitationDeliveryCipher::class),
            $container->get(Clock::class),
            $container->get(EventDispatcher::class),
            $container->get(AuditEvidenceRepository::class)
        ));
    $container->set('authorization.inspection.connection', static fn(Container $container): Connection =>
        DriverManager::getConnection($container->get(Connection::class)->getParams()));
    $container->set(MembershipProgress::class, static fn(Container $container): MembershipProgress =>
        new PostgresMembershipProgress($container->get('authorization.inspection.connection')));
    $container->set(InviteOrdinaryUserHandler::class, static fn(Container $container): InviteOrdinaryUserHandler =>
        new InviteOrdinaryUserHandler(
            $container->get(CommandBus::class),
            $container->get(InstallationAssignmentPolicy::class),
            $container->get(MembershipProgress::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(MembershipEvidenceRepository::class)
        ));
    $container->set(AddUserRoleHandler::class, static fn(Container $container): AddUserRoleHandler =>
        new AddUserRoleHandler(
            $container->get(CommandBus::class),
            $container->get(InstallationAssignmentPolicy::class),
            $container->get(MembershipProgress::class),
            $container->get(TransactionalUnitOfWork::class),
            $container->get(MembershipEvidenceRepository::class)
        ));
    $container->set(
        InstallationAssignmentPolicy::class,
        static fn(Container $container): InstallationAssignmentPolicy => new InstallationAssignmentPolicy(
            $container->get(QueryBus::class),
            $container->get(ManagedPolicyCatalog::class)
        )
    );
    $container->get('messaging.command.router')->registerHandlers([
        InvitePendingUserHandler::commandRegistration()  => InvitePendingUserHandler::class,
        InviteOrdinaryUserHandler::commandRegistration() => InviteOrdinaryUserHandler::class,
        AddUserRoleHandler::commandRegistration()        => AddUserRoleHandler::class
    ]);
    $container->set(GetRoleByIdHandler::class, static fn(Container $container): GetRoleByIdHandler =>
        new GetRoleByIdHandler($container->get(RoleRepository::class)));
    $container->set(GetPermissionByIdHandler::class, static fn(Container $container): GetPermissionByIdHandler =>
        new GetPermissionByIdHandler($container->get(PermissionRepository::class)));
    $container->get('messaging.query.router')->registerHandlers([
        GetRoleByIdHandler::queryRegistration()       => GetRoleByIdHandler::class,
        GetPermissionByIdHandler::queryRegistration() => GetPermissionByIdHandler::class
    ]);
    $container->set(InviteUserCommand::class, static fn(Container $container): InviteUserCommand =>
        new InviteUserCommand($container->get(CommandBus::class), $container->get(MembershipProgress::class)));
    $container->set(AddUserRoleCommand::class, static fn(Container $container): AddUserRoleCommand =>
        new AddUserRoleCommand($container->get(CommandBus::class), $container->get(MembershipProgress::class)));
    $container->set(SafeConsoleApplication::class, static function (Container $container): SafeConsoleApplication {
        $application = new SafeConsoleApplication('Fight Agent OS');
        $application->addCommand($container->get(InviteUserCommand::class));
        $application->addCommand($container->get(AddUserRoleCommand::class));
        $application->addCommand($container->get(BootstrapSuperAdminCommand::class));
        $application->addCommand($container->get(BootstrapWorkspaceCommand::class));
        $application->addCommand($container->get(PruneSessionPresentationCommand::class));
        $application->setAutoExit(false);

        return $application;
    });
};
