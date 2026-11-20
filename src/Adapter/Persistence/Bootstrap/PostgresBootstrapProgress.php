<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Bootstrap;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Bootstrap\BootstrapProgress;
use App\Domain\Bootstrap\BootstrapState;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Doctrine\DBAL\Connection;

/**
 * Class PostgresBootstrapProgress
 *
 * Reads committed step facts without claims or locks
 */
final readonly class PostgresBootstrapProgress implements BootstrapProgress
{
    /**
     * Constructs PostgresBootstrapProgress
     */
    public function __construct(private Connection $inspection)
    {
    }

    /**
     * @inheritDoc
     */
    public function inspect(BootstrapSuperAdmin $command): BootstrapState
    {
        // A separate read-only snapshot survives a rollback or closed write manager.
        return $this->inspection->transactional(function () use ($command): BootstrapState {
            $this->inspection->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');

            return $this->inspectSnapshot($command);
        });
    }

    /**
     * Reads the target, exact membership and completion evidence in one committed snapshot
     */
    private function inspectSnapshot(BootstrapSuperAdmin $command): BootstrapState
    {
        $user = $this->inspection->fetchAssociative('SELECT email, state FROM users WHERE id = ?', [
            $command->userId->toString()
        ]);
        if ($user === false) {
            return new BootstrapState('admitted', 'missing', 'missing');
        }
        if ($user['email'] !== $command->email->toString() || $user['state'] !== 'pending_activation') {
            return new BootstrapState('admitted');
        }
        $roles = $this->inspection->fetchFirstColumn('SELECT role_id FROM user_role_assignments WHERE user_id = ?', [
            $command->userId->toString()
        ]);
        $completed = $this->inspection->fetchOne(
            <<<'SQL'
SELECT 1 FROM audit_evidence WHERE actor_id = ? AND subject_id = ? AND subject_type = 'user' AND action = ?
SQL,
            [$command->operator->auditActorId, $command->userId->toString(), 'user.bootstrap_completed']
        ) !== false;

        return new BootstrapState(
            'admitted',
            'confirmed',
            $roles === [InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID] ? 'confirmed' : 'missing',
            $completed ? 'confirmed' : 'unconfirmed'
        );
    }
}
