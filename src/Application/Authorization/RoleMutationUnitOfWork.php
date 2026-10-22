<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Domain\Authorization\RoleMutationAudit;
use Fight\AccessControl\Domain\AccessControl\Role\Event\CustomRoleCreated;
use Fight\AccessControl\Domain\AccessControl\Role\Event\CustomRoleRemoved;
use Fight\AccessControl\Domain\AccessControl\Role\Event\CustomRoleRenamed;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;

/**
 * Class RoleMutationUnitOfWork
 *
 * Couples required consumer audit to the package transaction without nesting or early success publication
 */
final readonly class RoleMutationUnitOfWork implements TransactionalUnitOfWork
{
    /**
     * Constructs RoleMutationUnitOfWork
     */
    public function __construct(private TransactionalUnitOfWork $transactions, private RoleMutationAudit $audit)
    {
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        return $this->transactions->commitTransactional(function () use ($operation): mixed {
            $result = $operation();
            if (
                $result instanceof CustomRoleCreated
                || $result instanceof CustomRoleRenamed
                || $result instanceof CustomRoleRemoved
            ) {
                $this->audit->record($result);
            }

            return $result;
        });
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return $this->transactions->isClosed();
    }
}
