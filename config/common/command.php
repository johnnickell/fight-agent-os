<?php

declare(strict_types=1);

use App\Application\Authorization\AuthenticatedRoleAssignmentGuard;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\CommandHandler\DeliverUserInvitationHandler;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\CommandHandler\ExpireInvitationDeliveryHandler;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\EmailChangeGrant\CommandHandler\ExpireEmailChangeHandler;
use Fight\AccessControl\Application\AccessControl\ManagedPolicy\CommandHandler\ReconcileManagedPolicyHandler;
use Fight\AccessControl\Application\AccessControl\ManagedPolicy\Service\ManagedPolicyPlanner;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\CommandHandler\DeliverPasswordResetHandler;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\CommandHandler\ExpirePasswordResetDeliveryHandler;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\AssignRoleToUserHandler;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\DeliverUserInvitation;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\ExpireInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\Command\ExpireEmailChange;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\DeliverPasswordReset;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\ExpirePasswordResetDelivery;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return [
    'services' => [
        'messaging.command.router'                => static function (Container $container): ServiceAwareCommandRouter {
            return new ServiceAwareCommandRouter($container);
        },
        'messaging.command.routing'               => static function (Container $container): RoutingCommandBus {
            return new RoutingCommandBus($container->get('messaging.command.router'));
        },
        'messaging.command.authorized_routing'    => static function (Container $container): CommandPipeline {
            $routing = new CommandPipeline($container->get('messaging.command.routing'));
            $routing->addFilter($container->get(AuthenticatedRoleAssignmentGuard::class));

            return $routing;
        },
        'messaging.command.bus'                   => static fn(Container $container): CommandPipeline =>
            new CommandPipeline($container->get('messaging.command.authorized_routing')),
        CommandBus::class                         => static function (Container $container): CommandBus {
            return $container->get('messaging.command.bus');
        },
        SynchronousCommandBus::class              => static function (Container $container): SynchronousCommandBus {
            return $container->get('messaging.command.bus');
        },
        AssignRoleToUserHandler::class            => static fn(Container $c): AssignRoleToUserHandler =>
            new AssignRoleToUserHandler(
                $c->get(UserRepository::class),
                $c->get(RoleRepository::class),
                $c->get(Clock::class),
                $c->get(TransactionalUnitOfWork::class),
                $c->get(EventDispatcher::class)
            ),
        ReconcileManagedPolicyHandler::class      => static function (
            Container $container
        ): ReconcileManagedPolicyHandler {
            return new ReconcileManagedPolicyHandler(
                $container->get(PermissionRepository::class),
                $container->get(RoleRepository::class),
                $container->get(ManagedPolicyPlanner::class),
                $container->get(TransactionalUnitOfWork::class),
                $container->get(EventDispatcher::class),
                $container->get(Clock::class)
            );
        },
        ExpireInvitationDeliveryHandler::class    => static function (
            Container $container
        ): ExpireInvitationDeliveryHandler {
            return new ExpireInvitationDeliveryHandler(
                $container->get(ActivationGrantRepository::class),
                $container->get(TransactionalUnitOfWork::class),
                $container->get(EventDispatcher::class)
            );
        },
        ExpirePasswordResetDeliveryHandler::class => static function (
            Container $container
        ): ExpirePasswordResetDeliveryHandler {
            return new ExpirePasswordResetDeliveryHandler(
                $container->get(PasswordResetGrantRepository::class),
                $container->get(TransactionalUnitOfWork::class),
                $container->get(EventDispatcher::class)
            );
        },
        ExpireEmailChangeHandler::class           => static function (Container $container): ExpireEmailChangeHandler {
            return new ExpireEmailChangeHandler(
                $container->get(UserRepository::class),
                $container->get(EmailChangeGrantRepository::class),
                $container->get(TransactionalUnitOfWork::class),
                $container->get(EventDispatcher::class)
            );
        },
        DeliverUserInvitationHandler::class       => static function (
            Container $container
        ): DeliverUserInvitationHandler {
            return new DeliverUserInvitationHandler(
                $container->get(ActivationGrantRepository::class),
                $container->get(AuditEvidenceRepository::class),
                $container->get(TransactionalUnitOfWork::class),
                $container->get(InvitationDeliveryCipher::class),
                $container->get(CredentialDeliveryProvider::class),
                $container->get(Clock::class),
                $container->get(EventDispatcher::class)
            );
        },
        DeliverPasswordResetHandler::class        => static function (
            Container $container
        ): DeliverPasswordResetHandler {
            return new DeliverPasswordResetHandler(
                $container->get(PasswordResetGrantRepository::class),
                $container->get(AuditEvidenceRepository::class),
                $container->get(TransactionalUnitOfWork::class),
                $container->get(PasswordResetDeliveryCipher::class),
                $container->get(CredentialDeliveryProvider::class),
                $container->get(Clock::class),
                $container->get(EventDispatcher::class)
            );
        }
    ],
    'handlers' => [
        AssignRoleToUser::class            => AssignRoleToUserHandler::class,
        ReconcileManagedPolicy::class      => ReconcileManagedPolicyHandler::class,
        ExpireInvitationDelivery::class    => ExpireInvitationDeliveryHandler::class,
        ExpirePasswordResetDelivery::class => ExpirePasswordResetDeliveryHandler::class,
        ExpireEmailChange::class           => ExpireEmailChangeHandler::class,
        DeliverUserInvitation::class       => DeliverUserInvitationHandler::class,
        DeliverPasswordReset::class        => DeliverPasswordResetHandler::class
    ],
    'filters'  => []
];
