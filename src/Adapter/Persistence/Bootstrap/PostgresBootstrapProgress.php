<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Bootstrap;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Bootstrap\BootstrapEvidence;
use App\Domain\Bootstrap\BootstrapProgress;
use App\Domain\Bootstrap\BootstrapState;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use LogicException;

/**
 * Class PostgresBootstrapProgress
 *
 * Reads committed step facts and separately appends completion evidence without claims or locks
 */
final readonly class PostgresBootstrapProgress implements BootstrapProgress
{
    /**
     * Constructs PostgresBootstrapProgress
     */
    public function __construct(
        private Connection $connection,
        private Connection $inspection,
        private AuditEvidenceRepository $audit
    ) {
    }

    /**
     * @inheritDoc
     */
    public function complete(BootstrapSuperAdmin $command): void
    {
        $state = $this->inspect($command);
        if (
            $this->connection->isTransactionActive()
            || $state->invitation !== 'confirmed' || $state->assignment !== 'confirmed'
        ) {
            throw new LogicException('Bootstrap completion is unavailable.');
        }
        $this->connection->transactional(fn() => $this->audit->add(BootstrapEvidence::completed($command)));
    }

    /**
     * @inheritDoc
     */
    public function inspect(BootstrapSuperAdmin $command): BootstrapState
    {
        // A separate connection survives a rollback/closed manager and sees only committed state.
        $user = $this->inspection->fetchAssociative('SELECT email, state FROM users WHERE id = ?', [
            $command->userId->toString()
        ]);
        if ($user === false) {
            return new BootstrapState('admitted', 'missing', 'missing');
        }
        if ($user['email'] !== $command->input->email->toString() || $user['state'] !== 'pending_activation') {
            return new BootstrapState('admitted');
        }
        $roles = $this->inspection->fetchFirstColumn('SELECT role_id FROM user_role_assignments WHERE user_id = ?', [
            $command->userId->toString()
        ]);
        $completed = $this->inspection->fetchOne(
            <<<'SQL'
SELECT 1 FROM audit_evidence WHERE actor_id = ? AND subject_id = ? AND subject_type = 'user' AND action = ?
SQL,
            ['bootstrap:'.$command->input->operatorId, $command->userId->toString(), 'user.bootstrap_completed']
        ) !== false;

        return new BootstrapState(
            'admitted',
            'confirmed',
            $roles === [InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID] ? 'confirmed' : 'missing',
            $completed ? 'confirmed' : 'unconfirmed'
        );
    }
}
