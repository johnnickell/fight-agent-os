<?php

declare(strict_types=1);

use App\Adapter\Console\InviteUserCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\CredentialDelivery\RandomActivationCredentialGenerator;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PostgresInvitationTransaction;
use App\Application\Invitation\InvitationTransaction;
use App\Application\Invitation\InviteOrdinaryUserHandler;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\ActivationCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\AssignRoleToUserHandler;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\InvitePendingUserHandler;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Event\RoleAssignedToUser;
use Fight\AccessControl\Domain\AccessControl\User\Event\UserInvited;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Utility\ClassName;

// Console registrations extend the existing container; HTTP does not register this entry point.
return static function (Container $container): void {
    $container->set(ActivationCredentialGenerator::class, static fn(): ActivationCredentialGenerator =>
        new RandomActivationCredentialGenerator());
    $container->set(PostgresInvitationTransaction::class, static fn(Container $c): PostgresInvitationTransaction =>
        new PostgresInvitationTransaction(
            $c->get(Connection::class),
            $c->get(TransactionalUnitOfWork::class),
            $c->get(AuthorizationReferenceFences::class),
            $c->get(EventDispatcher::class)
        ));
    $container->set(InvitationTransaction::class, static fn(Container $c): InvitationTransaction =>
        $c->get(PostgresInvitationTransaction::class));
    $container->set('invitation.events.deferred', static function (Container $c): SimpleEventDispatcher {
        $dispatcher = new SimpleEventDispatcher();
        $transaction = $c->get(PostgresInvitationTransaction::class);
        $dispatcher->addHandler(ClassName::underscore(UserInvited::class), $transaction->defer(...));
        $dispatcher->addHandler(ClassName::underscore(RoleAssignedToUser::class), $transaction->defer(...));
        // Private CommandFailedEvent payloads are deliberately not forwarded.

        return $dispatcher;
    });
    $container->set(InvitePendingUserHandler::class, static fn(Container $c): InvitePendingUserHandler =>
        new InvitePendingUserHandler(
            $c->get(UserRepository::class),
            $c->get(ActivationGrantRepository::class),
            $c->get(AuditEvidenceRepository::class),
            $c->get(PostgresInvitationTransaction::class),
            $c->get(ActivationCredentialGenerator::class),
            $c->get(InvitationDeliveryCipher::class),
            $c->get(Clock::class),
            $c->get('invitation.events.deferred')
        ));
    $container->set(AssignRoleToUserHandler::class, static fn(Container $c): AssignRoleToUserHandler =>
        new AssignRoleToUserHandler(
            $c->get(UserRepository::class),
            $c->get(RoleRepository::class),
            $c->get(Clock::class),
            $c->get(PostgresInvitationTransaction::class),
            $c->get('invitation.events.deferred')
        ));
    $container->set(InviteOrdinaryUserHandler::class, static fn(Container $c): InviteOrdinaryUserHandler =>
        new InviteOrdinaryUserHandler(
            $c->get(InvitationTransaction::class),
            $c->get(QueryBus::class),
            $c->get(CommandBus::class),
            $c->get(AuditEvidenceRepository::class)
        ));
    $container->get('messaging.command.router')->registerHandlers([
        InviteOrdinaryUserHandler::commandRegistration() => InviteOrdinaryUserHandler::class,
        InvitePendingUser::class                         => InvitePendingUserHandler::class,
        AssignRoleToUser::class                          => AssignRoleToUserHandler::class
    ]);
    $container->set(InviteUserCommand::class, static fn(Container $c): InviteUserCommand =>
        new InviteUserCommand($c->get(CommandBus::class), $c->get(QueryBus::class)));
    $container->set(SafeConsoleApplication::class, static function (Container $c): SafeConsoleApplication {
        $application = new SafeConsoleApplication('Fight Agent OS');
        $application->addCommand($c->get(InviteUserCommand::class));
        $application->setAutoExit(false);

        return $application;
    });
};
