<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class PostgresUniqueConstraintRace
{
    /**
     * Executes one statement and maps only the named unique-constraint race to no change
     *
     * @param Closure(): int $statement
     */
    public static function execute(
        Connection $connection,
        string $constraintName,
        Closure $statement
    ): int {
        $savepoint = $connection->isTransactionActive()
            ? sprintf('authority_name_replacement_%d', spl_object_id($connection))
            : null;

        if ($savepoint !== null) {
            $connection->createSavepoint($savepoint);
        }

        try {
            $result = $statement();
        } catch (UniqueConstraintViolationException $exception) {
            if (!str_contains(
                $exception->getMessage(),
                sprintf('unique constraint "%s"', $constraintName)
            )) {
                throw $exception;
            }

            if ($savepoint !== null) {
                $connection->rollbackSavepoint($savepoint);
                $connection->releaseSavepoint($savepoint);
            }

            return 0;
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
