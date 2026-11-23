<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\Workspace\Workspace\PostgresWorkspaceRepository;
use App\Domain\Workspace\Workspace\InitialWorkspaceAlreadyExists;
use App\Domain\Workspace\Workspace\Workspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PgSQL\Exception as PgSqlException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects initial Workspace identity, creation time and conflict semantics
 */
final class PostgresWorkspaceRepositoryTest extends TestCase
{
    /**
     * Preserves the initial selection and the scoped lookup independently
     */
    public function testReadsOnlyTheRequestedWorkspace(): void
    {
        $workspace = $this->workspace();
        $requested = WorkspaceId::generate();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchAssociative')->willReturnCallback(
            static function (string $sql, array $parameters = []) use ($workspace, $requested): array|false {
                if ($parameters === []) {
                    self::assertStringContainsString("installation_slot = 'initial'", $sql);

                    return ['id' => $workspace->id->toString(), 'created_at' => '2026-10-20 12:00:00.654321+00:00'];
                }
                self::assertSame([$requested->toString()], $parameters);

                return false;
            }
        );
        $repository = new PostgresWorkspaceRepository($connection);

        $initial = $repository->findInitial();

        self::assertEquals($workspace, $initial);
        self::assertNull($repository->getById($requested));
    }

    /**
     * Rejects a detached write rather than silently creating an unaudited Workspace
     */
    public function testCreationRequiresCallerTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::never())->method('insert');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Workspace persistence requires an enclosing transaction.');
        (new PostgresWorkspaceRepository($connection))->add($this->workspace());
    }

    /**
     * Stores the initial claim with the aggregate's precise creation time
     */
    public function testWritesTheInitialClaim(): void
    {
        $workspace = $this->workspace();
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('insert')->with('workspaces', [
            'id'                => $workspace->id->toString(),
            'installation_slot' => 'initial',
            'created_at'        => '2026-10-20 12:00:00.654321+00:00'
        ])->willReturn(1);

        (new PostgresWorkspaceRepository($connection))->add($workspace);
    }

    /**
     * Translates only the installation-slot conflict without swallowing other database failures
     */
    #[DataProvider('conflicts')]
    public function testClassifiesConflict(string $constraint, bool $initialClaim): void
    {
        $driver = new PgSqlException('duplicate key value violates unique constraint "'.$constraint.'"', '23505');
        $failure = new UniqueConstraintViolationException($driver, null);
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('insert')->willThrowException($failure);

        if ($initialClaim) {
            $this->expectException(InitialWorkspaceAlreadyExists::class);
        }
        try {
            (new PostgresWorkspaceRepository($connection))->add($this->workspace());
            self::fail('A duplicate write must not report success');
        } catch (UniqueConstraintViolationException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    /**
     * Supplies the owned conflict and a distinct database constraint
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function conflicts(): iterable
    {
        yield 'installation claim' => ['uq_workspaces_installation_slot', true];
        yield 'primary key' => ['workspaces_pkey', false];
    }

    /**
     * Builds a Workspace with an independently fixed microsecond timestamp
     */
    private function workspace(): Workspace
    {
        return new Workspace(WorkspaceId::generate(), new DateTimeImmutable('2026-10-20T12:00:00.654321Z'));
    }
}
