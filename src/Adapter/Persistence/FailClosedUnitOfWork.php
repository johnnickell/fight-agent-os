<?php

declare(strict_types=1);

namespace App\Adapter\Persistence;

use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Repository\UnitOfWork;
use LogicException;

final readonly class FailClosedUnitOfWork implements UnitOfWork
{
    /**
     * Constructs FailClosedUnitOfWork
     */
    public function __construct(private TransactionalUnitOfWork $transactionalUnitOfWork)
    {
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        return $this->transactionalUnitOfWork->commitTransactional($operation);
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return $this->transactionalUnitOfWork->isClosed();
    }

    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        throw new LogicException('Unscoped persistence commits are not supported.');
    }
}
