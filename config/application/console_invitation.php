<?php

declare(strict_types=1);

use App\Adapter\CredentialDelivery\RandomActivationCredentialGenerator;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PostgresInvitationTransaction;
use App\Application\Invitation\InviteOrdinaryUser;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\ActivationCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\AssignRoleToUserHandler;
use Fight\AccessControl\Application\AccessControl\User\CommandHandler\InvitePendingUserHandler;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\Event\RoleAssignedToUser;
use Fight\AccessControl\Domain\AccessControl\User\Event\UserInvited;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Utility\ClassName;

// Loaded only by the trusted CLI, never by HTTP composition or generic package dispatch.
return static function (Container $container): InviteOrdinaryUser {
    if (!$container->has(ActivationCredentialGenerator::class)) {
        $container->set(ActivationCredentialGenerator::class, static fn(): ActivationCredentialGenerator =>
            new RandomActivationCredentialGenerator());
    }
    $transaction = new PostgresInvitationTransaction(
        $container->get(Connection::class),
        $container->get(TransactionalUnitOfWork::class),
        $container->get(AuthorizationReferenceFences::class),
        $container->get(EventDispatcher::class)
    );
    $deferredEvents = new SimpleEventDispatcher();
    $deferredEvents->addHandler(ClassName::underscore(UserInvited::class), $transaction->defer(...));
    $deferredEvents->addHandler(ClassName::underscore(RoleAssignedToUser::class), $transaction->defer(...));

    // CommandFailedEvent can contain private exception text: it is deliberately not forwarded.
    return new InviteOrdinaryUser(
        $transaction,
        $container->get(QueryBus::class),
        new InvitePendingUserHandler(
            $container->get(UserRepository::class),
            $container->get(ActivationGrantRepository::class),
            $container->get(AuditEvidenceRepository::class),
            $transaction,
            $container->get(ActivationCredentialGenerator::class),
            $container->get(InvitationDeliveryCipher::class),
            $container->get(Clock::class),
            $deferredEvents
        ),
        new AssignRoleToUserHandler(
            $container->get(UserRepository::class),
            $container->get(RoleRepository::class),
            $container->get(Clock::class),
            $transaction,
            $deferredEvents
        ),
        $container->get(AuditEvidenceRepository::class)
    );
};
