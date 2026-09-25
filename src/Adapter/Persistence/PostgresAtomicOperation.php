<?php

declare(strict_types=1);

namespace App\Adapter\Persistence;

use Closure;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Runs one adapter-owned multi-statement operation atomically
 *
 * When an enclosing transaction is active the operation is wrapped in a savepoint, so a known conflict can be
 * mapped to a contract-safe result without leaving a partial write. Without a transaction the operation runs
 * directly; repositories that require authoritative atomicity guard for that case themselves.
 */
final class PostgresAtomicOperation
{
    /**
     * Executes the operation, rolling back its savepoint before any failure propagates
     *
     * @param Closure(): mixed $operation
     */
    public static function execute(Connection $connection, Closure $operation): mixed
    {
        $savepoint = $connection->isTransactionActive()
            ? sprintf('atomic_operation_%d_%d', spl_object_id($connection), spl_object_id($operation))
            : null;

        if ($savepoint !== null) {
            $connection->createSavepoint($savepoint);
        }

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            if ($savepoint !== null) {
                $connection->rollbackSavepoint($savepoint);
                $connection->releaseSavepoint($savepoint);
            }

            throw $exception;
        }

        if ($savepoint !== null) {
            $connection->releaseSavepoint($savepoint);
        }

        return $result;
    }

    private function __construct()
    {
    }
}