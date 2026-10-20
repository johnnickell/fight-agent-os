<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Workspace;

use App\Application\Failure\RateLimited;
use App\Application\Workspace\RegistrationStore;
use App\Application\Workspace\RegistrationView;
use App\Application\Workspace\WorkspaceView;
use App\Domain\Workspace\RegistrationConflict;
use App\Domain\Workspace\RegistrationIntent;
use App\Domain\Workspace\RegistrationMetadata;
use App\Domain\Workspace\RegistrationSources;
use App\Domain\Workspace\RepositoryId;
use App\Domain\Workspace\RepositoryRegistration;
use App\Domain\Workspace\SourceDescriptor;
use App\Domain\Workspace\WorkspaceId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Messaging\MessageId;
use LogicException;

/**
 * Class PostgresRegistrationStore
 *
 * Persists bounded current state and append-only owned evidence on the canonical connection
 */
final readonly class PostgresRegistrationStore implements RegistrationStore
{
    /**
     * Constructs PostgresRegistrationStore
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function initialWorkspace(): ?WorkspaceView
    {
        $row = $this->connection->fetchAssociative(
            "SELECT id, created_at FROM workspaces WHERE installation_slot = 'initial'"
        );

        return $row === false ? null : new WorkspaceView(
            WorkspaceId::fromString($row['id']),
            new DateTimeImmutable($row['created_at'])
        );
    }

    /**
     * @inheritDoc
     */
    public function holdInitialWorkspace(): void
    {
        $this->requireTransaction();
        if ($this->connection->fetchOne('SHOW transaction_isolation') !== 'read committed') {
            throw new LogicException('Workspace writes require READ COMMITTED isolation.');
        }
        // One explicit installation Workspace today; future writers must retain the scoped fence.
        $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24130, 1)');
    }

    /**
     * @inheritDoc
     */
    public function bootstrap(WorkspaceView $workspace, UserId $operator): void
    {
        $this->requireTransaction();
        $this->connection->insert('workspaces', [
            'id'                => $workspace->id->toString(),
            'installation_slot' => 'initial',
            'created_at'        => $workspace->createdAt->format('Y-m-d H:i:s.uP')
        ]);
        $this->evidence(
            $workspace->id,
            null,
            $operator,
            $workspace->createdAt,
            'bootstrap_workspace',
            'initial-workspace',
            hash('sha256', 'bootstrap_workspace_v1'),
            [
                'workspace_id' => $workspace->id->toString(),
                'created_at'   => $workspace->createdAt->format('Y-m-d\TH:i:s.uP')
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function original(WorkspaceId $workspace, UserId $actor, RegistrationIntent $intent): ?RegistrationView
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
SELECT request_digest, original_result FROM workspace_operations
WHERE workspace_id = ? AND actor_type = 'user' AND actor_id = ?
AND command_kind = 'register_repository' AND idempotency_key = ?
SQL, [$workspace->toString(), $actor->toString(), $intent->idempotencyKey]);
        if ($row === false) {
            return null;
        }
        if (!hash_equals($row['request_digest'], $intent->digest())) {
            throw new RegistrationConflict('idempotency');
        }
        // Project the immutable original, never today's mutable registration or arbitrary stored JSON.
        $result = json_decode($row['original_result'], true, 32, JSON_THROW_ON_ERROR);
        $primary = null;
        if ($result['primary_source'] !== null) {
            $primary = SourceDescriptor::reconstitute($result['primary_source']);
        }
        $equivalent = array_values(array_map(
            SourceDescriptor::reconstitute(...),
            $result['equivalent_sources']
        ));
        $registration = RepositoryRegistration::reconstitute(
            RepositoryId::fromString($result['repository_id']),
            WorkspaceId::fromString($result['workspace_id']),
            new RegistrationMetadata($result['display_name'], $result['slug']),
            new RegistrationSources($primary, $equivalent),
            UserId::fromString($result['created_by']),
            new DateTimeImmutable($result['created_at'])
        );
        $view = RegistrationView::fromRegistration($registration);
        if (
            !$workspace->equals($registration->workspaceId) || !$actor->equals($registration->actorId)
            || $view->data !== $result
        ) {
            throw new LogicException('Unsupported original registration result.');
        }

        return $view;
    }

    /**
     * @inheritDoc
     */
    public function conflicts(WorkspaceId $workspace, string $slug, RegistrationSources $sources): array
    {
        $conflicts = [];
        if (
            $this->connection->fetchOne(<<<'SQL'
SELECT 1 FROM repository_registrations
WHERE workspace_id = ? AND canonical_slug = ? AND lifecycle = 'active'
SQL, [$workspace->toString(), $slug]) !== false
        ) {
            $conflicts[] = 'slug';
        }
        foreach ($sources->all() as $source) {
            if (
                $this->connection->fetchOne(<<<'SQL'
SELECT 1 FROM repository_sources
WHERE workspace_id = ? AND descriptor_version = 1 AND source_key = ? AND active
SQL, [$workspace->toString(), $source->key()]) !== false
            ) {
                $conflicts[] = 'source';
                break;
            }
        }

        return $conflicts;
    }

    /**
     * @inheritDoc
     */
    public function add(RepositoryRegistration $registration, RegistrationIntent $intent): RegistrationView
    {
        $this->requireTransaction();
        try {
            $date = $registration->createdAt->format('Y-m-d H:i:s.uP');
            $this->connection->insert('repository_registrations', [
                'id'                 => $registration->id->toString(),
                'workspace_id'       => $registration->workspaceId->toString(),
                'display_name'       => $registration->metadata->displayName,
                'canonical_slug'     => $registration->metadata->slug,
                'lifecycle'          => 'active',
                'metadata_revision'  => 1,
                'source_revision'    => 1,
                'lifecycle_revision' => 1,
                'created_by'         => $registration->actorId->toString(),
                'updated_by'         => $registration->actorId->toString(),
                'created_at'         => $date,
                'updated_at'         => $date
            ]);
            foreach ($registration->sources->all() as $index => $source) {
                $this->connection->insert('repository_sources', [
                    'workspace_id'       => $registration->workspaceId->toString(),
                    'repository_id'      => $registration->id->toString(),
                    'descriptor_version' => 1,
                    'source_key'         => $source->key(),
                    'source_role'        => $index === 0 ? 'primary' : 'equivalent',
                    'active'             => 1
                ]);
            }
            $view = RegistrationView::fromRegistration($registration);
            $this->evidence(
                $registration->workspaceId,
                $registration->id,
                $registration->actorId,
                $registration->createdAt,
                'register_repository',
                $intent->idempotencyKey,
                $intent->digest(),
                $view->data
            );

            return $view;
        } catch (UniqueConstraintViolationException $exception) {
            $known = [
                'uq_workspace_repository_active_slug'   => 'slug',
                'uq_workspace_repository_active_source' => 'source'
            ];
            foreach ($known as $constraint => $reason) {
                if (str_contains($exception->getMessage(), 'unique constraint "'.$constraint.'"')) {
                    throw new RegistrationConflict($reason);
                }
            }
            throw $exception;
        }
    }

    /**
     * @inheritDoc
     */
    public function get(WorkspaceId $workspace, RepositoryId $repository): ?RegistrationView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM repository_registrations WHERE workspace_id = ? AND id = ?',
            [$workspace->toString(), $repository->toString()]
        );

        return $row === false ? null : $this->view($row);
    }

    /**
     * @inheritDoc
     */
    public function page(WorkspaceId $workspace, int $offset, int $limit): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')->from('repository_registrations')
            ->where('workspace_id = :workspace')->setParameter('workspace', $workspace->toString())
            ->orderBy('created_at')->addOrderBy('id')
            ->setFirstResult($offset)->setMaxResults($limit + 1)->fetchAllAssociative();

        return [
            'items'    => array_map($this->view(...), array_slice($rows, 0, $limit)),
            'has_more' => count($rows) > $limit
        ];
    }

    /**
     * @inheritDoc
     */
    public function admitReference(UserId $actor, DateTimeImmutable $now): void
    {
        $this->requireTransaction();
        $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24130, 2)');
        $window = intdiv($now->getTimestamp(), 60) * 60;
        $this->connection->executeStatement(
            'DELETE FROM reference_generation_limits WHERE window_start < ?',
            [$window]
        );
        if ((int) $this->connection->fetchOne('SELECT count(*) FROM reference_generation_limits') >= 10000) {
            throw new RateLimited(60);
        }
        $count = $this->connection->fetchOne(<<<'SQL'
INSERT INTO reference_generation_limits (actor_id, window_start, attempts) VALUES (?, ?, 1)
ON CONFLICT (actor_id) DO UPDATE SET attempts = reference_generation_limits.attempts + 1
WHERE reference_generation_limits.attempts < 10 RETURNING attempts
SQL, [$actor->toString(), $window]);
        if ($count === false) {
            throw new RateLimited(60 - ($now->getTimestamp() - $window));
        }
    }

    /**
     * Reconstitutes approved canonical sources and rejects unsupported persisted state
     *
     * @param array<string, mixed> $row
     */
    private function view(array $row): RegistrationView
    {
        if (
            $row['lifecycle'] !== 'active' || (int) $row['metadata_revision'] !== 1
            || (int) $row['source_revision'] !== 1 || (int) $row['lifecycle_revision'] !== 1
        ) {
            throw new LogicException('Unsupported repository registration state.');
        }
        $primary = null;
        $equivalent = [];
        $sources = $this->connection->fetchAllAssociative(
            'SELECT source_key, source_role FROM repository_sources WHERE repository_id = ? ORDER BY source_key',
            [$row['id']]
        );
        foreach ($sources as $sourceRow) {
            $source = SourceDescriptor::reconstitute(json_decode(
                $sourceRow['source_key'],
                true,
                32,
                JSON_THROW_ON_ERROR
            ));
            if ($sourceRow['source_role'] === 'primary') {
                $primary = $source;
            } else {
                $equivalent[] = $source;
            }
        }

        return RegistrationView::fromRegistration(RepositoryRegistration::reconstitute(
            RepositoryId::fromString($row['id']),
            WorkspaceId::fromString($row['workspace_id']),
            new RegistrationMetadata($row['display_name'], $row['canonical_slug']),
            new RegistrationSources($primary, $equivalent),
            UserId::fromString($row['created_by']),
            new DateTimeImmutable($row['created_at'])
        ));
    }

    /**
     * Appends one original result and its owned audit effect in the current transaction
     *
     * @phpstan-param array<string, mixed> $result
     */
    private function evidence(
        WorkspaceId $workspace,
        ?RepositoryId $repository,
        UserId $actor,
        DateTimeImmutable $at,
        string $kind,
        string $key,
        string $digest,
        array $result
    ): void {
        $id = MessageId::generate()->toString();
        $actorType = $repository === null ? 'installation_operator' : 'user';
        $date = $at->format('Y-m-d H:i:s.uP');
        $this->connection->insert('workspace_operations', [
            'id'              => $id,
            'workspace_id'    => $workspace->toString(),
            'repository_id'   => $repository?->toString(),
            'actor_type'      => $actorType,
            'actor_id'        => $actor->toString(),
            'command_kind'    => $kind,
            'idempotency_key' => $key,
            'request_digest'  => $digest,
            'original_result' => json_encode($result, JSON_THROW_ON_ERROR),
            'event_name'      => $repository === null ? null : 'RepositoryRegistered',
            'created_at'      => $date
        ]);
        $this->connection->insert('workspace_audit', [
            'operation_id'         => $id,
            'workspace_id'         => $workspace->toString(),
            'repository_id'        => $repository?->toString(),
            'actor_type'           => $actorType,
            'actor_id'             => $actor->toString(),
            'action'               => $repository === null ? 'workspace_bootstrapped' : 'repository_registered',
            'predecessor_revision' => 0,
            'result_revision'      => 1,
            'result_code'          => 'created',
            'created_at'           => $date
        ]);
    }

    /**
     * Rejects repository writes outside their owning transaction
     */
    private function requireTransaction(): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Workspace persistence requires an enclosing transaction.');
        }
    }
}
