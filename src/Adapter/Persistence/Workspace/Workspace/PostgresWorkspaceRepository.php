<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Workspace\Workspace;

use App\Domain\Workspace\Workspace\InitialWorkspaceAlreadyExists;
use App\Domain\Workspace\Workspace\Workspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use App\Domain\Workspace\Workspace\WorkspaceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use LogicException;

/**
 * Class PostgresWorkspaceRepository
 *
 * Persists Workspace roots on the shared transaction connection
 */
final readonly class PostgresWorkspaceRepository implements WorkspaceRepository
{
    /**
     * Constructs PostgresWorkspaceRepository
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function findInitial(): ?Workspace
    {
        return $this->workspace($this->connection->fetchAssociative(
            "SELECT id, created_at FROM workspaces WHERE installation_slot = 'initial'"
        ));
    }

    /**
     * @inheritDoc
     */
    public function getById(WorkspaceId $workspaceId): ?Workspace
    {
        return $this->workspace($this->connection->fetchAssociative(
            'SELECT id, created_at FROM workspaces WHERE id = ?',
            [$workspaceId->toString()]
        ));
    }

    /**
     * @inheritDoc
     */
    public function add(Workspace $workspace): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Workspace persistence requires an enclosing transaction.');
        }
        try {
            $this->connection->insert('workspaces', [
                'id'                => $workspace->id->toString(),
                'installation_slot' => 'initial',
                'created_at'        => $workspace->createdAt->format('Y-m-d H:i:s.uP')
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'unique constraint "uq_workspaces_installation_slot"')) {
                throw new InitialWorkspaceAlreadyExists();
            }
            throw $exception;
        }
    }

    /**
     * Reconstitutes current state without returning persistence rows
     *
     * @param array<string, mixed>|false $row
     */
    private function workspace(array|false $row): ?Workspace
    {
        return $row === false ? null : new Workspace(
            WorkspaceId::fromString($row['id']),
            new DateTimeImmutable($row['created_at'])
        );
    }
}
