<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\Workspace\Project\PostgresProjectRepository;
use App\Domain\Workspace\Project\Project;
use App\Domain\Workspace\Project\ProjectConflict;
use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\ProjectMetadata;
use App\Domain\Workspace\Project\ProjectSources;
use App\Domain\Workspace\Project\SourceDescriptor;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PgSQL\Exception as PgSqlException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects Project persistence mapping independently of database constraints
 */
final class PostgresProjectRepositoryTest extends TestCase
{
    /**
     * Restores authoritative revisions, actors and the primary source independently of row order
     */
    public function testRestoresAuthoritativeProjectFacts(): void
    {
        $workspace = WorkspaceId::generate();
        $project = ProjectId::generate();
        $row = $this->row($workspace, $project);
        $connection = $this->createMock(Connection::class);
        $scope = [$workspace->toString(), $project->toString()];
        $connection->expects(self::once())->method('fetchAssociative')->with(self::anything(), $scope)
            ->willReturn($row);
        $connection->expects(self::once())->method('fetchAllAssociative')->with(self::anything(), $scope)
            ->willReturn([
                ['source_role' => 'equivalent', 'source_key' => $this->source('mirror')->key()],
                ['source_role' => 'primary', 'source_key' => $this->source('primary')->key()]
            ]);

        $restored = (new PostgresProjectRepository($connection))->getById($workspace, $project);

        self::assertNotNull($restored);
        self::assertSame($project->toString(), $restored->id->toString());
        self::assertSame($workspace->toString(), $restored->workspaceId->toString());
        self::assertSame('project-alpha', $restored->metadata->slug);
        $revisions = [$restored->metadataRevision, $restored->sourceRevision, $restored->lifecycleRevision];
        self::assertSame([2, 3, 4], $revisions);
        self::assertSame($row['created_by'], $restored->createdBy->toString());
        self::assertSame($row['updated_by'], $restored->updatedBy->toString());
        self::assertEquals(new DateTimeImmutable('2026-10-21T12:00:00.123456Z'), $restored->updatedAt);
        self::assertSame(['primary', 'mirror'], array_map(
            static fn (SourceDescriptor $source): string => (string) $source->toArray()['repository'],
            $restored->sources->all()
        ));
    }

    /**
     * Maps a scoped page to Projects and retains the ordering supplied by the query
     */
    public function testScopedPage(): void
    {
        $workspace = WorkspaceId::generate();
        $first = ProjectId::generate();
        $second = ProjectId::generate();
        $result = $this->createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            $this->row($workspace, $first), $this->row($workspace, $second)
        ]);
        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn(new QueryBuilder($connection));
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->expects(self::once())->method('executeQuery')->with(self::callback(
            static fn (string $sql): bool => str_contains($sql, 'WHERE workspace_id = :workspace ')
                && preg_match('/ORDER BY created_at(?: ASC)?, id(?: ASC)? LIMIT 2 OFFSET 3\z/', $sql) === 1
        ), ['workspace' => $workspace->toString()])->willReturn($result);
        $connection->method('fetchAllAssociative')->willReturn([
            ['source_role' => 'primary', 'source_key' => $this->source('primary')->key()]
        ]);

        $page = new PostgresProjectRepository($connection)->slice($workspace, 3, 2);
        self::assertSame([$first->toString(), $second->toString()], array_map(
            static fn (Project $project): string => $project->id->toString(),
            $page
        ));
    }

    /**
     * Passes the workspace and exact canonical claim to persistence, not only a display value
     */
    #[DataProvider('claimChecks')]
    public function testActiveClaim(string $kind, bool $exists): void
    {
        $workspace = WorkspaceId::generate();
        $source = $this->source('primary');
        $value = $kind === 'slug' ? 'project-alpha' : $source->key();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with(self::anything(), [$workspace->toString(), $value])
            ->willReturn($exists ? 1 : false);
        $repository = new PostgresProjectRepository($connection);
        $actual = $kind === 'slug' ? $repository->hasActiveSlug($workspace, $value) : $repository->hasActiveSource(
            $workspace,
            $source
        );
        self::assertSame($exists, $actual);
    }

    /**
     * Supplies present and absent results independently for both claim types
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function claimChecks(): iterable
    {
        foreach (['slug', 'source'] as $kind) {
            yield "$kind exists" => [$kind, true];
            yield "$kind missing" => [$kind, false];
        }
    }

    /**
     * Treats a missing scoped row as absent without reading related source claims
     */
    public function testMissingProjectDoesNotReadSources(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects(self::never())->method('fetchAllAssociative');

        $repository = new PostgresProjectRepository($connection);
        self::assertNull($repository->getById(WorkspaceId::generate(), ProjectId::generate()));
    }

    /**
     * Refuses to represent unknown stored lifecycle values as active
     */
    public function testUnknownStoredLifecycleIsNotRestored(): void
    {
        $workspace = WorkspaceId::generate();
        $project = ProjectId::generate();
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            ...$this->row($workspace, $project), 'lifecycle' => 'archived'
        ]);
        $connection->expects(self::never())->method('fetchAllAssociative');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsupported Project lifecycle.');
        (new PostgresProjectRepository($connection))->getById($workspace, $project);
    }

    /**
     * Reports an invalid persisted canonical descriptor without including the stored bytes
     */
    public function testInvalidStoredSourceFailsClosed(): void
    {
        $workspace = WorkspaceId::generate();
        $project = ProjectId::generate();
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->row($workspace, $project));
        $connection->method('fetchAllAssociative')->willReturn([
            ['source_role' => 'primary', 'source_key' => '{"type":"unknown","locator":"private-value"}']
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid persisted Project facts.');
        (new PostgresProjectRepository($connection))->getById($workspace, $project);
    }

    /**
     * Keeps Project and source-claim writes inside the caller transaction
     */
    public function testRejectsDetachedWrite(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::never())->method('insert');

        $this->expectException(LogicException::class);
        (new PostgresProjectRepository($connection))->add(Project::register(
            ProjectId::generate(),
            WorkspaceId::generate(),
            new ProjectMetadata('Project Alpha', 'project-alpha'),
            new ProjectSources($this->source('primary')),
            UserId::generate(),
            new DateTimeImmutable('2026-10-20T12:00:00Z')
        ));
    }

    /**
     * Persists primary and equivalent claims with the same Project and Workspace identities
     */
    public function testWritesDistinctClaimsForTheProject(): void
    {
        $project = $this->project();
        $claims = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::exactly(3))->method('insert')->willReturnCallback(
            static function (string $table, array $row) use (&$claims): int {
                $claims[$table][] = $row;

                return 1;
            }
        );

        (new PostgresProjectRepository($connection))->add($project);

        self::assertSame($project->id->toString(), $claims['projects'][0]['id']);
        self::assertSame('project-alpha', $claims['projects'][0]['canonical_slug']);
        self::assertSame(['primary', 'equivalent'], array_column($claims['project_sources'], 'source_role'));
        self::assertSame([
            $this->source('primary')->key(), $this->source('mirror')->key()
        ], array_column($claims['project_sources'], 'source_key'));
        self::assertSame([$project->id->toString(), $project->id->toString()], array_column(
            $claims['project_sources'],
            'project_id'
        ));
        self::assertSame([$project->workspaceId->toString(), $project->workspaceId->toString()], array_column(
            $claims['project_sources'],
            'workspace_id'
        ));
    }

    /**
     * Translates only the two public claim conflicts; unknown constraints retain their original failure
     */
    #[DataProvider('claimConstraints')]
    public function testClassifiesUniqueConstraintFailures(string $constraint, ?string $reason): void
    {
        $driver = new PgSqlException('duplicate key value violates unique constraint "'.$constraint.'"', '23505');
        $failure = new UniqueConstraintViolationException($driver, null);
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('insert')->willThrowException($failure);

        try {
            (new PostgresProjectRepository($connection))->add($this->project());
            self::fail('Expected persistence to reject the conflicting Project.');
        } catch (ProjectConflict $conflict) {
            self::assertSame($reason, $conflict->reason);
        } catch (UniqueConstraintViolationException $exception) {
            self::assertNull($reason);
            self::assertSame($failure, $exception);
        }
    }

    /**
     * Distinguishes Project claims from unrelated storage constraints
     *
     * @return iterable<string, array{string, ?string}>
     */
    public static function claimConstraints(): iterable
    {
        yield 'slug' => ['uq_workspace_project_active_slug', 'slug'];
        yield 'source' => ['uq_workspace_project_active_source', 'source'];
        yield 'unrelated' => ['projects_pkey', null];
    }

    /**
     * Supplies a Project with two explicitly distinct sources
     */
    private function project(): Project
    {
        return Project::register(
            ProjectId::generate(),
            WorkspaceId::generate(),
            new ProjectMetadata('Project Alpha', 'project-alpha'),
            new ProjectSources($this->source('primary'), [$this->source('mirror')]),
            UserId::generate(),
            new DateTimeImmutable('2026-10-20T12:00:00Z')
        );
    }

    /**
     * Supplies stored values whose identities and revisions distinguish their roles
     *
     * @return array<string, string>
     */
    private function row(WorkspaceId $workspace, ProjectId $project): array
    {
        return [
            'id'                 => $project->toString(),
            'workspace_id'       => $workspace->toString(),
            'display_name'       => 'Project Alpha',
            'canonical_slug'     => 'project-alpha',
            'lifecycle'          => 'active',
            'created_by'         => UserId::generate()->toString(),
            'updated_by'         => UserId::generate()->toString(),
            'created_at'         => '2026-10-20T12:00:00Z',
            'updated_at'         => '2026-10-21T12:00:00.123456Z',
            'metadata_revision'  => '2',
            'source_revision'    => '3',
            'lifecycle_revision' => '4'
        ];
    }

    /**
     * Supplies a canonical GitHub source on the same owner with a distinct repository name
     */
    private function source(string $name): SourceDescriptor
    {
        $locator = 'https://github.com/team/'.$name;

        return SourceDescriptor::fromInput(['type' => 'github_repository_v1', 'locator' => $locator]);
    }
}
