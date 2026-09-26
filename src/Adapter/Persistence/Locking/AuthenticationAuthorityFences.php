<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Locking;

use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;

/**
 * Class AuthenticationAuthorityFences
 *
 * Serializes authentication-authority mutation for a single user
 *
 * Holds a transaction-scoped PostgreSQL advisory lock derived from a collision-resistant key so that authority
 * replacement, email-change confirmation, lifecycle mutation, and coupled authority/session insertion cannot
 * interleave. The lock is released with the enclosing transaction.
 */
final readonly class AuthenticationAuthorityFences
{
    private const int NAMESPACE = 24122;

    /**
     * Constructs AuthenticationAuthorityFences
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Acquires the per-user authentication-authority fence
     */
    public function hold(UserId $userId): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('The authentication-authority fence requires an enclosing transaction.');
        }

        $this->connection->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, ?))',
            [$userId->toString(), self::NAMESPACE]
        );
    }
}
