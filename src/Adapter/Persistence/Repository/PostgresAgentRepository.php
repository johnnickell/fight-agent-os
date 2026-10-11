<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\Agent\Agent;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentCredentialId;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentId;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentName;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentRepository;
use Fight\AccessControl\Domain\AccessControl\Agent\Exception\AgentOperationRejectedException;
use Fight\AccessControl\Domain\AccessControl\Agent\Operation\AgentOperationContract;
use Fight\AccessControl\Domain\AccessControl\Agent\Operation\AgentOperationFailure;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use SensitiveParameter;

/**
 * Class PostgresAgentRepository
 *
 * Observes Agent references without admitting unsupported credential-operation writes
 */
final readonly class PostgresAgentRepository implements AgentRepository
{
    /**
     * Constructs PostgresAgentRepository
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function getOperationContract(): AgentOperationContract
    {
        // No operation storage, destination history, protected sink or qualified cohort is installed.
        $this->unavailable();
    }

    /**
     * @inheritDoc
     */
    public function getById(AgentId $id): ?Agent
    {
        if ($this->connection->fetchOne('SELECT 1 FROM agents WHERE id = ?', [$id->toString()]) !== false) {
            $this->unavailable();
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getByCredentialId(AgentCredentialId $credentialId): ?Agent
    {
        if (
            $this->connection->fetchOne(
                'SELECT 1 FROM agents WHERE credential_id = ?',
                [$credentialId->toString()]
            ) !== false
        ) {
            $this->unavailable();
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getAll(Pagination $pagination): ResultSet
    {
        if ($this->connection->fetchOne('SELECT 1 FROM agents LIMIT 1') !== false) {
            $this->unavailable();
        }

        return new ResultSet(
            $pagination->page(),
            $pagination->perPage(),
            0,
            ArrayList::of(Agent::class)
        );
    }

    /**
     * @inheritDoc
     */
    public function hasPermissionAssignment(PermissionId $permissionId): bool
    {
        // Existing assignments must block promotion even when the issuing operation is unavailable.
        if (
            $this->connection->fetchOne(
                'SELECT 1 FROM agent_permission_assignments WHERE permission_id = ? LIMIT 1',
                [$permissionId->toString()]
            ) !== false
        ) {
            return true;
        }

        // Incomplete Agent authority cannot be treated as proof that an assignment is absent.
        if ($this->connection->fetchOne('SELECT 1 FROM agents LIMIT 1') !== false) {
            $this->unavailable();
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function rename(AgentId $id, AgentName $name, Closure $now): ?DateTimeImmutable
    {
        $this->unavailable();
    }

    /**
     * @inheritDoc
     */
    public function replace(
        #[SensitiveParameter] Agent $expected,
        #[SensitiveParameter] Agent $replacement
    ): bool {
        $this->unavailable();
    }

    /**
     * @inheritDoc
     */
    public function validatePermissionAssignments(array $expectedPermissions): bool
    {
        $this->unavailable();
    }

    /**
     * @inheritDoc
     */
    public function replacePermissionAssignments(
        #[SensitiveParameter] Agent $expected,
        #[SensitiveParameter] Agent $replacement
    ): bool {
        $this->unavailable();
    }

    /**
     * @inheritDoc
     */
    public function add(#[SensitiveParameter] Agent $agent): void
    {
        $this->unavailable();
    }

    /**
     * Rejects unsupported operation writes and uncorrelated Agent reads without exposing secrets
     */
    private function unavailable(): never
    {
        throw new AgentOperationRejectedException(AgentOperationFailure::UNAVAILABLE);
    }
}
