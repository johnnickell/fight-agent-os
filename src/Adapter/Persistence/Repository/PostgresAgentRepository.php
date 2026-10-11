<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use App\Adapter\Persistence\PostgresUniqueConstraintRace;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\Agent\Agent;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentCredentialId;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentId;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentName;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentRepository;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentState;
use Fight\AccessControl\Domain\AccessControl\Agent\Exception\AgentOperationRejectedException;
use Fight\AccessControl\Domain\AccessControl\Agent\Exception\AgentUpdateException;
use Fight\AccessControl\Domain\AccessControl\Agent\Operation\AgentOperationContract;
use Fight\AccessControl\Domain\AccessControl\Agent\Operation\AgentOperationFailure;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use SensitiveParameter;

/**
 * Class PostgresAgentRepository
 *
 * Persists Agent authority on the canonical PostgreSQL connection
 */
final readonly class PostgresAgentRepository implements AgentRepository
{
    /**
     * Constructs PostgresAgentRepository
     */
    public function __construct(
        private Connection $connection,
        private AuthorizationReferenceFences $referenceFences
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getOperationContract(): AgentOperationContract
    {
        // Credential delivery and operation-cohort admission belong to future integration work.
        throw new AgentOperationRejectedException(AgentOperationFailure::UNAVAILABLE);
    }

    /**
     * @inheritDoc
     */
    public function getById(AgentId $id): ?Agent
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM agents WHERE id = ?', [$id->toString()]);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function getByCredentialId(AgentCredentialId $credentialId): ?Agent
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM agents WHERE credential_id = ?',
            [$credentialId->toString()]
        );

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function getAll(Pagination $pagination): ResultSet
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('agents')
            ->orderBy('created_at')
            ->addOrderBy('id')
            ->setMaxResults($pagination->limit())
            ->setFirstResult($pagination->offset())
            ->fetchAllAssociative();

        return new ResultSet(
            $pagination->page(),
            $pagination->perPage(),
            (int) $this->connection->createQueryBuilder()->select('COUNT(*)')->from('agents')->fetchOne(),
            ArrayList::of(Agent::class)->replace(array_map($this->hydrate(...), $rows))
        );
    }

    /**
     * @inheritDoc
     */
    public function hasPermissionAssignment(PermissionId $permissionId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM agent_permission_assignments WHERE permission_id = ? LIMIT 1',
            [$permissionId->toString()]
        ) !== false;
    }

    /**
     * @inheritDoc
     */
    public function rename(AgentId $id, AgentName $name, Closure $now): ?DateTimeImmutable
    {
        $this->referenceFences->holdPermissionReferences();
        $current = $this->locked($id);
        if ($current === null || $current->getState() !== AgentState::ACTIVE) {
            throw new AgentUpdateException('The Agent is unavailable for a name update.');
        }

        $successor = $current->rename($name, $now());
        if ($successor === $current) {
            return null;
        }

        $this->connection->executeStatement(
            'UPDATE agents SET name = ?, updated_at = ? WHERE id = ?',
            [$name->toString(), $this->date($successor->getUpdatedAt()), $id->toString()]
        );

        return $successor->getUpdatedAt();
    }

    /**
     * @inheritDoc
     */
    public function replace(
        #[SensitiveParameter] Agent $expected,
        #[SensitiveParameter] Agent $replacement
    ): bool {
        $this->referenceFences->holdPermissionReferences();
        $current = $this->locked($expected->getId());
        if (
            $current === null || !$this->matches($current, $expected)
            || !$expected->canReplaceCredentialWith($replacement)
        ) {
            return false;
        }

        // No authority outside this successor is rewritten; conflicts fail without changing the predecessor.
        return PostgresUniqueConstraintRace::execute(
            $this->connection,
            'agents_credential_id_key',
            fn(): int => (int) $this->connection->executeStatement(<<<'SQL'
UPDATE agents SET state = :state, credential_id = :credential_id,
    credential_revision = :credential_revision,
    encrypted_hmac_shared_secret_envelope = :encrypted_hmac_shared_secret_envelope,
    updated_at = :updated_at WHERE id = :id
SQL, $this->values($replacement))
        ) === 1;
    }

    /**
     * @inheritDoc
     */
    public function validatePermissionAssignments(array $expectedPermissions): bool
    {
        $this->referenceFences->holdPermissionReferences();
        foreach ($expectedPermissions as $expected) {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM permissions WHERE id = ?',
                [$expected->getId()->toString()]
            );
            if (
                $row === false || $row['name'] !== $expected->getName()->toString()
                || (bool) $row['managed'] !== $expected->isManaged()
                || $row['tier'] !== $expected->getTier()->value
                || $row['tier'] !== PermissionTier::ADMIN_SAFE->value
                || new DateTimeImmutable((string) $row['created_at']) != $expected->getCreatedAt()
                || new DateTimeImmutable((string) $row['updated_at']) != $expected->getUpdatedAt()
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function replacePermissionAssignments(
        #[SensitiveParameter] Agent $expected,
        #[SensitiveParameter] Agent $replacement
    ): bool {
        $this->referenceFences->holdPermissionReferences();
        $current = $this->locked($expected->getId());
        if (
            $current === null || !$this->matches($current, $expected)
            || $expected->getState() !== AgentState::ACTIVE
            || !$expected->getId()->equals($replacement->getId())
            || !$expected->getName()->equals($replacement->getName())
            || $expected->getState() !== $replacement->getState()
            || !$expected->getCredentialId()->equals($replacement->getCredentialId())
            || $expected->getCredentialRevision() !== $replacement->getCredentialRevision()
            || !hash_equals(
                $expected->getEncryptedHmacSharedSecretEnvelope(),
                $replacement->getEncryptedHmacSharedSecretEnvelope()
            )
            || $expected->getCreatedAt() != $replacement->getCreatedAt()
            || $replacement->getUpdatedAt() < $expected->getUpdatedAt()
            || $replacement->getPermissionAssignmentRevision() !== $expected->getPermissionAssignmentRevision() + 1
            || $this->samePermissions($expected, $replacement)
            || !$this->permissionsAreSafe($replacement->getPermissionIds())
        ) {
            return false;
        }

        $this->connection->executeStatement(
            'DELETE FROM agent_permission_assignments WHERE agent_id = ?',
            [$expected->getId()->toString()]
        );
        foreach ($replacement->getPermissionIds() as $permissionId) {
            $this->connection->insert('agent_permission_assignments', [
                'agent_id'      => $expected->getId()->toString(),
                'permission_id' => $permissionId->toString()
            ]);
        }

        return $this->connection->executeStatement(<<<'SQL'
UPDATE agents SET permission_assignment_revision = ?, updated_at = ? WHERE id = ?
SQL, [
            $replacement->getPermissionAssignmentRevision(),
            $this->date($replacement->getUpdatedAt()),
            $expected->getId()->toString()
        ]) === 1;
    }

    /**
     * @inheritDoc
     */
    public function add(#[SensitiveParameter] Agent $agent): void
    {
        $this->referenceFences->holdPermissionReferences();
        if (
            $agent->getState() !== AgentState::ACTIVE
            || $agent->getCredentialRevision() !== 0
            || $agent->getPermissionAssignmentRevision() !== 1
            || $agent->getPermissionIds() !== []
            || $agent->getCreatedAt() != $agent->getUpdatedAt()
        ) {
            throw new PersistenceConflict('Only new Agent authority may be inserted.');
        }
        $inserted = $this->connection->executeStatement(<<<'SQL'
INSERT INTO agents (id, name, state, credential_id, credential_revision,
    encrypted_hmac_shared_secret_envelope, permission_assignment_revision, created_at, updated_at)
VALUES (:id, :name, :state, :credential_id, :credential_revision,
    :encrypted_hmac_shared_secret_envelope, :permission_assignment_revision, :created_at, :updated_at)
ON CONFLICT DO NOTHING
SQL, $this->values($agent));
        if ($inserted !== 1) {
            throw new PersistenceConflict('The Agent identity or credential is already in use.');
        }
    }

    /**
     * Retrieves and locks one authoritative Agent through the enclosing transaction
     */
    private function locked(AgentId $id): ?Agent
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM agents WHERE id = ? FOR UPDATE', [$id->toString()]);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Reconstitutes one Agent with its direct Permission references
     *
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Agent
    {
        $permissions = $this->connection->fetchFirstColumn(<<<'SQL'
SELECT permission_id FROM agent_permission_assignments WHERE agent_id = ? ORDER BY permission_id
SQL, [(string) $row['id']]);

        return Agent::reconstitute(
            AgentId::fromString((string) $row['id']),
            AgentName::fromString((string) $row['name']),
            AgentState::from((string) $row['state']),
            AgentCredentialId::fromString((string) $row['credential_id']),
            (int) $row['credential_revision'],
            (string) $row['encrypted_hmac_shared_secret_envelope'],
            array_map(static fn(mixed $id): PermissionId => PermissionId::fromString((string) $id), $permissions),
            (int) $row['permission_assignment_revision'],
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at'])
        );
    }

    /**
     * Validates all current authority, including the encrypted envelope, without disclosing it
     */
    private function matches(Agent $current, Agent $expected): bool
    {
        return $this->values($current) === $this->values($expected)
            && $this->samePermissions($current, $expected);
    }

    /**
     * Validates direct Permission sets independently of their storage order
     */
    private function samePermissions(Agent $first, Agent $second): bool
    {
        $firstIds = array_map(static fn(PermissionId $id): string => $id->toString(), $first->getPermissionIds());
        $secondIds = array_map(static fn(PermissionId $id): string => $id->toString(), $second->getPermissionIds());
        sort($firstIds);
        sort($secondIds);

        return $firstIds === $secondIds;
    }

    /**
     * Checks Permission tiers under the shared transaction-duration reference fence
     *
     * @phpstan-param list<PermissionId> $ids
     */
    private function permissionsAreSafe(array $ids): bool
    {
        foreach ($ids as $id) {
            if (
                $this->connection->fetchOne(
                    'SELECT 1 FROM permissions WHERE id = ? AND tier = ? LIMIT 1',
                    [$id->toString(), PermissionTier::ADMIN_SAFE->value]
                ) === false
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Maps Agent authority to database parameters
     *
     * @return array<string, string|int>
     */
    private function values(Agent $agent): array
    {
        return [
            'id'                                    => $agent->getId()->toString(),
            'name'                                  => $agent->getName()->toString(),
            'state'                                 => $agent->getState()->value,
            'credential_id'                         => $agent->getCredentialId()->toString(),
            'credential_revision'                   => $agent->getCredentialRevision(),
            'encrypted_hmac_shared_secret_envelope' => $agent->getEncryptedHmacSharedSecretEnvelope(),
            'permission_assignment_revision'        => $agent->getPermissionAssignmentRevision(),
            'created_at'                            => $this->date($agent->getCreatedAt()),
            'updated_at'                            => $this->date($agent->getUpdatedAt())
        ];
    }

    /**
     * Formats a timestamp for PostgreSQL comparison
     */
    private function date(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }
}
