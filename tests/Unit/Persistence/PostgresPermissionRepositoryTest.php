<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\Permission\PostgresPermissionRepository;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\Common\Domain\Repository\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Protects permission state mapping and authority changes
 */
final class PostgresPermissionRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private PostgresPermissionRepository $repository;
    private Permission $custom;
    private Permission $managed;
    private DateTimeImmutable $at;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $this->connection->method('createQueryBuilder')->willReturnCallback(
            fn(): QueryBuilder => new QueryBuilder($this->connection)
        );
        $this->repository = new PostgresPermissionRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection)
        );
        $this->at = new DateTimeImmutable('2026-10-20T12:00:00.123456Z');
        $this->custom = Permission::define(
            PermissionId::generate(),
            PermissionName::fromString('SAMPLE_READ'),
            $this->at
        );
        $this->managed = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('MANAGED_READ'),
            PermissionTier::ADMIN_SAFE,
            $this->at
        );
    }

    /**
     * Maps creation fields and treats a rejected identity or name claim as a conflict
     */
    public function testInsertMapsAuthorityAndRejectsAConflict(): void
    {
        $this->connection->expects(self::exactly(2))->method('executeStatement')->with(
            self::stringContains('ON CONFLICT DO NOTHING'),
            $this->row($this->custom),
            ['managed' => ParameterType::BOOLEAN]
        )->willReturnOnConsecutiveCalls(1, 0);

        $this->repository->add($this->custom);
        $this->expectException(PersistenceConflict::class);
        $this->repository->add($this->custom);
    }

    /**
     * Omits missing and duplicate IDs without losing the caller's ordering
     */
    public function testIdLookupReturnsUniqueExistingPermissionsInInputOrder(): void
    {
        $missing = PermissionId::generate();
        $successor = $this->successor();
        $seen = [];
        $this->connection->expects(self::exactly(3))->method('executeQuery')->willReturnCallback(
            function (string $sql, array $parameters) use ($successor, $missing, &$seen): Result {
                self::assertStringContainsString('WHERE id = :value', $sql);
                $seen[] = $parameters['value'];
                $row = match ($parameters['value']) {
                    $missing->toString() => false,
                    $this->custom->getId()->toString() => $this->row($this->custom),
                    $successor->getId()->toString() => [...$this->row($successor), 'managed' => 't'],
                    default => self::fail('Unexpected permission ID')
                };
                $result = $this->createStub(Result::class);
                $result->method('fetchAssociative')->willReturn($row);

                return $result;
            }
        );

        $permissions = $this->repository->getByIds([
            $missing, $successor->getId(), $this->custom->getId(), $successor->getId(), $missing
        ]);

        self::assertEquals([$successor, $this->custom], $permissions);
        self::assertSame([
            $missing->toString(), $successor->getId()->toString(), $this->custom->getId()->toString()
        ], $seen);
    }

    /**
     * Refuses to hydrate a custom permission as protected authority
     */
    public function testNameLookupRejectsAnInvalidPersistedCustomTier(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchAssociative')->willReturn([
            ...$this->row($this->custom), 'tier' => PermissionTier::SUPER_ADMIN_ONLY->value
        ]);
        $this->connection->expects(self::once())->method('executeQuery')->with(
            self::stringContains('WHERE name = :value'),
            ['value' => $this->custom->getName()->toString()]
        )->willReturn($result);

        $this->expectException(UnexpectedValueException::class);
        $this->repository->getByName($this->custom->getName());
    }

    /**
     * Separates managed selection and stable page order from the total count
     */
    public function testListsPreserveManagedStateAndUseUnpagedCount(): void
    {
        $this->connection->expects(self::exactly(3))->method('executeQuery')->willReturnCallback(
            function (string $sql): Result {
                $result = $this->createStub(Result::class);
                if (str_contains($sql, 'COUNT(*)')) {
                    self::assertStringNotContainsString('LIMIT', $sql);
                    $result->method('fetchOne')->willReturn('8');
                } elseif (str_contains($sql, 'managed = TRUE')) {
                    $result->method('fetchAllAssociative')->willReturn([$this->row($this->managed)]);
                } else {
                    self::assertStringContainsString('ORDER BY created_at, id LIMIT 2 OFFSET 4', $sql);
                    $result->method('fetchAllAssociative')->willReturn([$this->row($this->custom)]);
                }

                return $result;
            }
        );

        $page = $this->repository->getAll(new Pagination(3, 2));

        self::assertSame(8, $page->totalRecords());
        self::assertEquals([$this->custom], iterator_to_array($page->records()));
        self::assertEquals([$this->managed], $this->repository->getManaged());
    }

    /**
     * Rejects a replacement that changes immutable authority without taking locks or writing
     */
    public function testReplacementCannotTransferManagedOwnership(): void
    {
        $replacement = Permission::defineManaged(
            $this->custom->getId(),
            $this->custom->getName(),
            PermissionTier::ADMIN_SAFE,
            $this->at
        );
        $this->connection->expects(self::never())->method('executeQuery');
        $this->connection->expects(self::never())->method('executeStatement');

        self::assertFalse($this->repository->replace($this->custom, $replacement));
    }

    /**
     * Prevents promotion while a direct Agent grant or ordinary Role still references the permission
     */
    #[DataProvider('unsafeReferences')]
    public function testProtectedPromotionRejectsUnsafeReferences(bool $agentReference): void
    {
        $this->expectFence();
        $reads = [];
        $this->connection->expects(self::exactly($agentReference ? 2 : 3))->method('fetchOne')->willReturnCallback(
            function (string $sql, array $parameters) use ($agentReference, &$reads): string|int|false {
                if ($sql === 'SHOW transaction_isolation') {
                    return 'read committed';
                }
                self::assertSame([$this->managed->getId()->toString()], $parameters);
                $reads[] = $sql;

                return !str_contains($sql, 'agent_permission_assignments') || $agentReference ? 1 : false;
            }
        );
        $this->connection->expects(self::never())->method('executeStatement');

        self::assertFalse($this->repository->replace($this->managed, $this->successor()));
        self::assertStringContainsString('agent_permission_assignments', $reads[0]);
        if (!$agentReference) {
            self::assertStringContainsString("r.name <> 'ROLE_SUPER_ADMIN'", $reads[1]);
        }
    }

    /**
     * Supplies unsafe promotion references
     *
     * @return iterable<string, array{bool}>
     */
    public static function unsafeReferences(): iterable
    {
        yield 'direct Agent grant' => [true];
        yield 'ordinary Role grant' => [false];
    }

    /**
     * Uses the old snapshot and sends the protected tier only after reference checks succeed
     */
    public function testPromotionUsesExpectedSnapshotAndReportsLostWrite(): void
    {
        $this->expectFence(2);
        $this->connection->expects(self::exactly(6))->method('fetchOne')->willReturnCallback(
            static fn(string $sql): string|false => $sql === 'SHOW transaction_isolation' ? 'read committed' : false
        );
        $successor = $this->successor();
        $this->connection->expects(self::exactly(2))->method('executeStatement')->with(
            self::stringContains('updated_at = :expected_updated_at'),
            [
                'id'                     => $this->managed->getId()->toString(),
                'expected_name'          => $this->managed->getName()->toString(),
                'expected_tier'          => PermissionTier::ADMIN_SAFE->value,
                'expected_managed'       => true,
                'expected_created_at'    => $this->at->format('Y-m-d H:i:s.uP'),
                'expected_updated_at'    => $this->at->format('Y-m-d H:i:s.uP'),
                'replacement_name'       => $successor->getName()->toString(),
                'replacement_tier'       => PermissionTier::SUPER_ADMIN_ONLY->value,
                'replacement_managed'    => true,
                'replacement_updated_at' => $successor->getUpdatedAt()->format('Y-m-d H:i:s.uP')
            ],
            ['expected_managed' => ParameterType::BOOLEAN, 'replacement_managed' => ParameterType::BOOLEAN]
        )->willReturnOnConsecutiveCalls(1, 0);

        self::assertTrue($this->repository->replace($this->managed, $successor));
        self::assertFalse($this->repository->replace($this->managed, $successor));
    }

    /**
     * Checks Feature references and guards deletion against every owned reference and a stale snapshot
     */
    public function testRemovalReportsOnlyAnActualDeletion(): void
    {
        $this->expectFence(2);
        $featureReferences = 0;
        $this->connection->expects(self::exactly(4))->method('fetchOne')->willReturnCallback(
            function (string $sql, array $parameters) use (&$featureReferences): string|false {
                if ($sql === 'SHOW transaction_isolation') {
                    return 'read committed';
                }
                self::assertStringContainsString('FROM features WHERE permission_id = ?', $sql);
                self::assertSame([$this->custom->getId()->toString()], $parameters);

                return ++$featureReferences === 1 ? '1' : false;
            }
        );
        $this->connection->expects(self::exactly(2))->method('executeStatement')->with(
            self::callback(static function (string $sql): bool {
                foreach (['role_permissions', 'features', 'agent_permission_assignments'] as $table) {
                    self::assertStringContainsString('NOT EXISTS (SELECT 1 FROM '.$table, $sql);
                }

                return str_contains($sql, 'updated_at = :updated_at');
            }),
            $this->row($this->custom),
            ['managed' => ParameterType::BOOLEAN]
        )->willReturnOnConsecutiveCalls(0, 1);

        self::assertTrue($this->repository->hasFeatureReference($this->custom->getId()));
        self::assertFalse($this->repository->hasFeatureReference($this->custom->getId()));
        self::assertFalse($this->repository->remove($this->custom));
        self::assertTrue($this->repository->remove($this->custom));
    }

    /**
     * Expects a permission reference lock for each attempted write
     */
    private function expectFence(int $count = 1): void
    {
        $this->connection->method('isTransactionActive')->willReturn(true);
        $this->connection->expects(self::exactly($count))->method('executeQuery')->with(
            'SELECT pg_advisory_xact_lock(?, ?)',
            [24120, 1]
        )->willReturn($this->createStub(Result::class));
    }

    /**
     * Builds the protected successor of the managed fixture
     */
    private function successor(): Permission
    {
        return $this->managed->reconcileManaged(
            PermissionName::fromString('MANAGED_PROTECTED'),
            PermissionTier::SUPER_ADMIN_ONLY,
            $this->at->modify('+1 second')
        );
    }

    /**
     * Describes persisted permission state
     *
     * @return array<string, string|bool>
     */
    private function row(Permission $permission): array
    {
        return [
            'id'         => $permission->getId()->toString(),
            'name'       => $permission->getName()->toString(),
            'tier'       => $permission->getTier()->value,
            'managed'    => $permission->isManaged(),
            'created_at' => $permission->getCreatedAt()->format('Y-m-d H:i:s.uP'),
            'updated_at' => $permission->getUpdatedAt()->format('Y-m-d H:i:s.uP')
        ];
    }
}
