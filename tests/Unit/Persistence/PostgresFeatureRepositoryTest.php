<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\Feature\PostgresFeatureRepository;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PgSQL\Exception as PgSqlException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\Feature\Exception\FeatureConflictException;
use Fight\AccessControl\Domain\AccessControl\Feature\Exception\FeatureReferenceException;
use Fight\AccessControl\Domain\AccessControl\Feature\Exception\FeatureStateException;
use Fight\AccessControl\Domain\AccessControl\Feature\Feature;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureId;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureName;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureStatus;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\Common\Domain\Repository\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects persistence of Feature state and its Permission reference
 */
final class PostgresFeatureRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private PostgresFeatureRepository $features;
    private Feature $feature;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('isTransactionActive')->willReturn(true);
        $this->connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $this->connection->method('createQueryBuilder')->willReturnCallback(
            fn(): QueryBuilder => new QueryBuilder($this->connection)
        );
        $this->features = new PostgresFeatureRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection)
        );
        $this->feature = Feature::define(
            FeatureId::generate(),
            FeatureName::fromString('test-feature'),
            PermissionId::generate()
        );
    }

    /**
     * Preserves stored status, revision and reference when restoring a named Feature
     */
    public function testLookupRestoresCompleteFeatureOrReturnsNoMatch(): void
    {
        $row = $this->row();
        $row['revision'] = '7';
        $row['status'] = FeatureStatus::ON->value;
        $this->connection->method('executeQuery')->willReturnCallback(
            function (string $sql, array $parameters) use ($row): Result {
                $result = $this->createStub(Result::class);
                if (str_contains($sql, 'WHERE name = :value')) {
                    self::assertSame(['value' => 'test-feature'], $parameters);
                    $result->method('fetchAssociative')->willReturn($row);
                } else {
                    self::assertStringContainsString('WHERE id = :value', $sql);
                    $result->method('fetchAssociative')->willReturn(false);
                }

                return $result;
            }
        );

        $found = $this->features->getByName($this->feature->getName());
        self::assertNotNull($found);
        self::assertTrue($this->feature->getId()->equals($found->getId()));
        self::assertTrue($this->feature->getPermissionId()->equals($found->getPermissionId()));
        self::assertSame(FeatureStatus::ON, $found->getStatus());
        self::assertSame(7, $found->getRevision());
        self::assertNull($this->features->getById(FeatureId::generate()));
    }

    /**
     * Does not treat an invalid persisted status as an enabled or absent Feature
     */
    public function testLookupRejectsCorruptPersistedState(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchAssociative')->willReturn(['status' => 'invalid'] + $this->row());
        $this->connection->method('executeQuery')->willReturn($result);

        $this->expectException(FeatureStateException::class);
        $this->features->getById($this->feature->getId());
    }

    /**
     * Preserves the total record count on an empty page past the end of the list
     */
    public function testEmptyPageStillReportsTheTotal(): void
    {
        $this->connection->method('executeQuery')->willReturnCallback(function (string $sql): Result {
            $result = $this->createStub(Result::class);
            if (str_contains($sql, 'COUNT(*)')) {
                $result->method('fetchOne')->willReturn('3');
            } else {
                self::assertStringContainsString('ORDER BY name, id LIMIT 2 OFFSET 4', $sql);
                $result->method('fetchAllAssociative')->willReturn([]);
            }

            return $result;
        });

        $page = $this->features->getAll(new Pagination(3, 2));

        self::assertCount(0, $page->records());
        self::assertSame(3, $page->totalRecords());
    }

    /**
     * Distinguishes unavailable references from duplicate identities on insert
     */
    #[DataProvider('insertOutcomes')]
    public function testRegistrationRequiresAReferenceAndUniqueIdentity(string $outcome): void
    {
        $this->reads(permissionPresent: $outcome !== 'missing');
        $write = $this->connection->expects(self::exactly($outcome === 'missing' ? 0 : 1))->method('executeStatement');
        if ($outcome !== 'missing') {
            $write->with(self::stringContains('INSERT INTO features'), $this->row());
            if ($outcome === 'concurrent deletion') {
                $write->willThrowException($this->missingReference());
            } else {
                $write->willReturn($outcome === 'duplicate' ? 0 : 1);
            }
        }
        if ($outcome !== 'accepted') {
            $this->expectException(
                $outcome === 'duplicate' ? FeatureConflictException::class : FeatureReferenceException::class
            );
        }

        $this->features->add($this->feature);
    }

    /**
     * Supplies distinct insert outcomes at the database boundary
     *
     * @return iterable<string, array{string}>
     */
    public static function insertOutcomes(): iterable
    {
        foreach (['accepted', 'missing', 'duplicate', 'concurrent deletion'] as $outcome) {
            yield $outcome => [$outcome];
        }
    }

    /**
     * Requires the current full snapshot and a valid reference before applying a one-step change
     */
    #[DataProvider('replacementOutcomes')]
    public function testReplacementDoesNotOverwriteUnexpectedState(string $outcome, bool $expected): void
    {
        $newPermission = PermissionId::generate();
        $replacement = match ($outcome) {
            'no change' => $this->feature,
            'revision jump' => $this->feature->withPermission($newPermission)->withStatus(FeatureStatus::ON),
            'different identity' => Feature::define(FeatureId::generate(), $this->feature->getName(), $newPermission),
            default => $this->feature->withPermission($newPermission)
        };
        $this->reads(snapshotCurrent: $outcome !== 'stale', permissionPresent: $outcome !== 'missing');
        $writes = in_array($outcome, ['updated', 'lost update', 'concurrent deletion'], true);
        $write = $this->connection->expects(self::exactly($writes ? 1 : 0))->method('executeStatement');
        if ($writes) {
            $write->with(self::stringContains('AND status = :old_status AND revision = :old_revision'), [
                'id'                => $this->feature->getId()->toString(),
                'name'              => 'test-feature',
                'old_permission_id' => $this->feature->getPermissionId()->toString(),
                'old_status'        => $this->feature->getStatus()->value,
                'old_revision'      => $this->feature->getRevision(),
                'permission_id'     => $newPermission->toString(),
                'status'            => $this->feature->getStatus()->value,
                'revision'          => $this->feature->getRevision() + 1
            ]);
            if ($outcome === 'concurrent deletion') {
                $write->willThrowException($this->missingReference());
            } else {
                $write->willReturn($outcome === 'updated' ? 1 : 0);
            }
        }
        if (in_array($outcome, ['missing', 'concurrent deletion'], true)) {
            $this->expectException(FeatureReferenceException::class);
        }

        self::assertSame($expected, $this->features->replace($this->feature, $replacement));
    }

    /**
     * Supplies replacement admission and write outcomes
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function replacementOutcomes(): iterable
    {
        foreach (['updated', 'no change'] as $outcome) {
            yield $outcome => [$outcome, true];
        }
        $rejections = ['revision jump', 'different identity', 'stale', 'lost update', 'missing', 'concurrent deletion'];
        foreach ($rejections as $outcome) {
            yield $outcome => [$outcome, false];
        }
    }

    /**
     * Does not report deletion when the expected state is no longer stored
     */
    public function testRemovalUsesTheExpectedDefinition(): void
    {
        $this->reads();
        $this->connection->expects(self::once())->method('executeStatement')->with(
            self::stringContains('AND status = :status AND revision = :revision'),
            $this->row()
        )->willReturn(0);

        self::assertFalse($this->features->remove($this->feature));
    }

    /**
     * Supplies the database-side permission and feature snapshots
     */
    private function reads(bool $snapshotCurrent = true, bool $permissionPresent = true): void
    {
        $this->connection->method('fetchOne')->willReturnCallback(
            static function (string $sql, array $parameters) use ($snapshotCurrent, $permissionPresent): mixed {
                if (str_contains($sql, 'FROM permissions')) {
                    return $permissionPresent ? 1 : false;
                }
                if ($sql === 'SHOW transaction_isolation') {
                    return 'read committed';
                }
                self::assertStringContainsString('AND status = :old_status AND revision = :old_revision', $sql);
                self::assertArrayHasKey('old_permission_id', $parameters);

                return $snapshotCurrent ? 1 : false;
            }
        );
    }

    /**
     * Returns a simulated PostgreSQL rejection of a removed reference
     */
    private function missingReference(): ForeignKeyConstraintViolationException
    {
        return new ForeignKeyConstraintViolationException(
            new PgSqlException('Missing Permission reference', '23503'),
            null
        );
    }

    /**
     * Supplies the current Feature in storage form
     *
     * @return array<string, string|int>
     */
    private function row(): array
    {
        return [
            'id'            => $this->feature->getId()->toString(),
            'name'          => 'test-feature',
            'permission_id' => $this->feature->getPermissionId()->toString(),
            'status'        => $this->feature->getStatus()->value,
            'revision'      => $this->feature->getRevision()
        ];
    }
}
