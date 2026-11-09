<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Domain\Authorization\EffectiveAdministrators;
use App\Domain\Authorization\InstallationManagedPolicy;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;

/**
 * Class PostgresEffectiveAdministrators
 *
 * Queries active administrators in one statement without serializing lifecycle commands
 */
final readonly class PostgresEffectiveAdministrators implements EffectiveAdministrators
{
    /**
     * Constructs PostgresEffectiveAdministrators
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function isLast(UserId $targetId): bool
    {
        $result = $this->connection->fetchAssociative(<<<'SQL'
WITH authority AS (
    SELECT id FROM roles WHERE id = :role_id AND managed = TRUE AND name = 'ROLE_SUPER_ADMIN'
), administrators AS (
    SELECT u.id FROM users u
    JOIN user_role_assignments a ON a.user_id = u.id
    JOIN authority r ON r.id = a.role_id
    WHERE u.state = 'active'
)
SELECT EXISTS (SELECT 1 FROM authority) AS authority_valid,
    EXISTS (SELECT 1 FROM administrators WHERE id = :target_id)
    AND NOT EXISTS (SELECT 1 FROM administrators WHERE id <> :target_id) AS last_administrator
SQL, ['role_id' => InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID, 'target_id' => $targetId->toString()]);
        if ($result === false || !$result['authority_valid']) {
            throw new LogicException('Managed administrator authority is unavailable.');
        }

        return (bool) $result['last_administrator'];
    }
}
