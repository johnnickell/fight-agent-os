<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Locking;

use Doctrine\DBAL\Connection;
use LogicException;

/**
 * Class AuthorizationReferenceFences
 */
final readonly class AuthorizationReferenceFences
{
    private const int NAMESPACE = 24120;
    private const int PERMISSION_REFERENCES = 1;
    private const int ROLE_REFERENCES = 2;

    /**
     * Constructs AuthorizationReferenceFences
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Acquires locks on referenced permissions during a write
     */
    public function holdPermissionReferences(): void
    {
        $this->hold(self::PERMISSION_REFERENCES);
    }

    /**
     * Acquires locks on referenced roles during a write
     */
    public function holdRoleReferences(): void
    {
        $this->hold(self::ROLE_REFERENCES);
    }

    /**
     * Acquires the authoritative record lock for the current transaction
     */
    private function hold(int $key): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Authorization reference fences require an enclosing transaction.');
        }

        $this->connection->executeQuery(
            'SELECT pg_advisory_xact_lock(?, ?)',
            [self::NAMESPACE, $key]
        );
    }
}
