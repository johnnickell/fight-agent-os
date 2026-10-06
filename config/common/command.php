<?php

declare(strict_types=1);

use Fight\AccessControl\Application\AccessControl\ActivationGrant\CommandHandler\DeliverUserInvitationHandler;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\CommandHandler\ExpireInvitationDeliveryHandler;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\EmailChangeGrant\CommandHandler\ExpireEmailChangeHandler;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\CommandHandler\DeliverPasswordResetHandler;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\CommandHandler\ExpirePasswordResetDeliveryHandler;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\DeliverUserInvitation;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\ExpireInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\Command\ExpireEmailChange;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\DeliverPasswordReset;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\ExpirePasswordResetDelivery;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
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
        'messaging.command.bus'                   => static function (Container $container): CommandPipeline {
            return new CommandPipeline($container->get('messaging.command.routing'));
        },
        CommandBus::class                         => static function (Container $container): CommandBus {
            return $container->get('messaging.command.bus');
        },
        SynchronousCommandBus::class              => static function (Container $container): SynchronousCommandBus {
            return $container->get('messaging.command.bus');
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
        ExpireInvitationDelivery::class    => ExpireInvitationDeliveryHandler::class,
        ExpirePasswordResetDelivery::class => ExpirePasswordResetDeliveryHandler::class,
        ExpireEmailChange::class           => ExpireEmailChangeHandler::class,
        DeliverUserInvitation::class       => DeliverUserInvitationHandler::class,
        DeliverPasswordReset::class        => DeliverPasswordResetHandler::class
    ],
    'filters'  => []
];
