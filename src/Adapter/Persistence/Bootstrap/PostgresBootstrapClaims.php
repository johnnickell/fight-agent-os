<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Bootstrap;

use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Bootstrap\BootstrapClaims;
use App\Domain\Bootstrap\BootstrapEvidence;
use App\Domain\Bootstrap\BootstrapState;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use LogicException;

/**
 * Class PostgresBootstrapClaims
 *
 * Serializes permanent claims independently of package business transactions
 */
final readonly class PostgresBootstrapClaims implements BootstrapClaims
{
    /**
     * Constructs PostgresBootstrapClaims
     */
    public function __construct(
        private Connection $connection,
        private Connection $inspection,
        private AuthorizationReferenceFences $references,
        private UserRepository $users,
        private AuditEvidenceRepository $audit
    ) {
    }

    /**
     * @inheritDoc
     */
    public function claim(BootstrapSuperAdmin $command): void
    {
        $this->requireIndependentTransaction();
        $this->connection->transactional(function () use ($command): void {
            $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24121, 1)');
            $this->references->holdRoleReferences();
            if (
                $this->connection->fetchOne('SHOW transaction_isolation') !== 'read committed'
                || $this->connection->fetchOne('SELECT 1 FROM installation_bootstrap') !== false
                || $this->users->hasRoleAssignment(RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID))
            ) {
                throw new LogicException('Bootstrap is unavailable.');
            }
            $this->connection->insert('installation_bootstrap', [
                'singleton'   => true,
                'operator_id' => $command->input->operatorId->toString(),
                'target_id'   => $command->userId->toString(),
                'email'       => $command->input->email->toString()
            ]);
            $this->audit->add(BootstrapEvidence::claimed($command));
        });
    }

    /**
     * @inheritDoc
     */
    public function complete(BootstrapSuperAdmin $command): void
    {
        $this->requireIndependentTransaction();
        $state = $this->inspect($command);
        if (
            $state->claim !== 'claimed' || $state->invitation !== 'confirmed'
            || $state->assignment !== 'confirmed' || $state->audit === 'confirmed'
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
        // A separate connection survives a rollback/closed manager and observes no uncommitted caller state.
        $claim = $this->inspection->fetchAssociative(
            'SELECT operator_id, target_id, email FROM installation_bootstrap'
        );
        if ($claim === false) {
            return new BootstrapState('absent');
        }
        if (
            $claim['operator_id'] !== $command->input->operatorId->toString()
            || $claim['target_id'] !== $command->userId->toString()
            || $claim['email'] !== $command->input->email->toString()
        ) {
            return new BootstrapState('unavailable');
        }
        $parameters = ['bootstrap:'.$command->input->operatorId, $command->userId->toString()];
        if (
            $this->inspection->fetchOne(
                <<<'SQL'
SELECT 1 FROM audit_evidence WHERE actor_id = ? AND subject_id = ? AND subject_type = 'user' AND action = ?
SQL,
                [...$parameters, 'user.bootstrap_claimed']
            ) === false
        ) {
            return new BootstrapState('unknown');
        }
        $user = $this->inspection->fetchAssociative('SELECT email, state FROM users WHERE id = ?', [
            $command->userId->toString()
        ]);
        if ($user === false) {
            return new BootstrapState('claimed', 'missing', 'missing');
        }
        if ($user['email'] !== $command->input->email->toString() || $user['state'] !== 'pending_activation') {
            return new BootstrapState('claimed');
        }
        $roles = $this->inspection->fetchFirstColumn('SELECT role_id FROM user_role_assignments WHERE user_id = ?', [
            $command->userId->toString()
        ]);
        $assigned = $roles === [InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID];
        $completed = $this->inspection->fetchOne(
            <<<'SQL'
SELECT 1 FROM audit_evidence WHERE actor_id = ? AND subject_id = ? AND subject_type = 'user' AND action = ?
SQL,
            [...$parameters, 'user.bootstrap_completed']
        ) !== false;

        return new BootstrapState(
            'claimed',
            'confirmed',
            $assigned ? 'confirmed' : 'missing',
            $completed ? 'confirmed' : 'unconfirmed'
        );
    }

    /**
     * Rejects ambient transactions rather than joining or nesting package work
     */
    private function requireIndependentTransaction(): void
    {
        if ($this->connection->isTransactionActive()) {
            throw new LogicException('Bootstrap requires an independent transaction.');
        }
    }
}
