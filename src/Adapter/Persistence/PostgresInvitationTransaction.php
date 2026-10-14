<?php

declare(strict_types=1);

namespace App\Adapter\Persistence;

use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Application\Invitation\InvitationTransaction;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\User\Event\RoleAssignedToUser;
use Fight\AccessControl\Domain\AccessControl\User\Event\UserInvited;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use LogicException;

/**
 * Class PostgresInvitationTransaction
 *
 * Enlists only the two console invitation handlers in one originating transaction
 *
 * This participant is never registered as the application's general unit of work
 */
final class PostgresInvitationTransaction implements InvitationTransaction, TransactionalUnitOfWork
{
    private bool $active = false;
    private bool $participating = false;
    /** @var list<UserInvited|RoleAssignedToUser> */
    private array $events = [];

    /**
     * Constructs PostgresInvitationTransaction
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly TransactionalUnitOfWork $root,
        private readonly AuthorizationReferenceFences $fences,
        private readonly EventDispatcher $dispatcher
    ) {
    }

    /**
     * @inheritDoc
     */
    public function commit(callable $operation): void
    {
        if ($this->active || $this->connection->isTransactionActive()) {
            throw new LogicException('Invitation scope requires its own originating transaction.');
        }

        try {
            $this->root->commitTransactional(function () use ($operation): void {
                $this->active = true;
                $this->fences->holdPermissionReferences();
                $this->fences->holdRoleReferences();
                $operation();
            });
            $events = $this->events;
        } finally {
            $this->active = false;
            $this->events = [];
        }

        // The normal subscriber can now commit a claim and invoke the provider outside a transaction.
        foreach ($events as $event) {
            $this->dispatcher->trigger($event);
        }
    }

    /**
     * Runs one package handler's transaction callback inside the owned originating scope
     *
     * No nested participant, independent commit or external callback is permitted
     */
    public function commitTransactional(callable $operation): mixed
    {
        if (!$this->active || $this->participating || !$this->connection->isTransactionActive()) {
            throw new LogicException('Invitation participant requires its active originating scope.');
        }

        $this->participating = true;
        try {
            return $operation();
        } finally {
            $this->participating = false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return $this->root->isClosed();
    }

    /**
     * Stores only safe success facts until the originating commit succeeds
     */
    public function defer(EventMessage $message): void
    {
        $event = $message->payload();
        if (!$this->active || !($event instanceof UserInvited || $event instanceof RoleAssignedToUser)) {
            throw new LogicException('Unsupported deferred invitation event.');
        }
        $this->events[] = $event;
    }
}
