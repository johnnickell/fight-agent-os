<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\Role\PostgresRoleRepository;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\Common\Domain\Repository\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects role state mapping and authoritative write admission
 */
final class PostgresRoleRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private PostgresRoleRepository $roles;
    private Role $role;
    private PermissionId $permission;
    private DateTimeImmutable $at;

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
        $this->roles = new PostgresRoleRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection)
        );
        $this->at = new DateTimeImmutable('2026-10-20T12:00:00.123456Z');
        $this->permission = PermissionId::generate();
        $this->role = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_SAMPLE'),
            [$this->permission],
            $this->at
        );
    }

    /**
     * Preserves role identity, revision, timestamp and membership and skips missing and repeated IDs
     */
    public function testIdLookupDeduplicatesAndRestoresCompleteState(): void
    {
        $missing = RoleId::generate();
        $reads = [];
        $this->connection->method('executeQuery')->willReturnCallback(
            function (string $sql, array $parameters) use ($missing, &$reads): Result {
                if (str_contains($sql, 'FROM role_permissions')) {
                    self::assertSame(['role_id' => $this->role->getId()->toString()], $parameters);

                    return $this->membership(true);
                }
                $id = $parameters['value'];
                $reads[] = $id;
                $result = $this->createStub(Result::class);
                $result->method('fetchAssociative')->willReturn($id === $missing->toString() ? false : $this->row());

                return $result;
            }
        );

        $roles = $this->roles->getByIds([$missing, $this->role->getId(), $missing, $this->role->getId()]);

        self::assertSame([$missing->toString(), $this->role->getId()->toString()], $reads);
        self::assertCount(1, $roles);
        self::assertTrue($this->role->hasSameState($roles[0]));
    }

    /**
     * Reads managed roles and permission references through their distinct selectors
     */
    public function testScopeSelectorsReturnHydratedRoles(): void
    {
        $role = Role::defineManaged(
            $this->role->getId(),
            RoleName::fromString('ROLE_MANAGED'),
            [$this->permission],
            $this->at
        );
        $seen = [];
        $this->connection->method('executeQuery')->willReturnCallback(
            function (string $sql, array $parameters) use ($role, &$seen): Result {
                if (str_contains($sql, 'FROM role_permissions')) {
                    return $this->membership(true);
                }
                $result = $this->createStub(Result::class);
                $row = $this->row($role);
                $row['managed'] = 't';
                if (str_contains($sql, 'WHERE name = :value')) {
                    self::assertSame(['value' => 'ROLE_MANAGED'], $parameters);
                    $result->method('fetchAssociative')->willReturn($row);
                    $seen[] = 'name';
                } else {
                    if (str_contains($sql, 'rp.permission_id')) {
                        self::assertSame(['id' => $this->permission->toString()], $parameters);
                        $seen[] = 'permission';
                    } else {
                        self::assertStringContainsString('managed = TRUE', $sql);
                        $seen[] = 'managed';
                    }
                    $result->method('fetchAllAssociative')->willReturn([$row]);
                }

                return $result;
            }
        );

        $named = $this->roles->getByName($role->getName());
        self::assertNotNull($named);
        self::assertTrue($role->hasSameState($named));
        foreach ([$this->roles->getManaged(), $this->roles->getContainingPermission($this->permission)] as $selected) {
            self::assertCount(1, $selected);
            self::assertTrue($role->hasSameState($selected[0]));
        }
        self::assertSame(['name', 'managed', 'permission'], $seen);
    }

    /**
     * Restricts a page independently of the total result count
     */
    public function testPaginationUsesStableOrderAndUnpagedTotal(): void
    {
        $this->connection->method('executeQuery')->willReturnCallback(
            function (string $sql): Result {
                if (str_contains($sql, 'FROM role_permissions')) {
                    return $this->membership(true);
                }
                $result = $this->createStub(Result::class);
                if (str_contains($sql, 'COUNT(*)')) {
                    self::assertStringNotContainsString('LIMIT', $sql);
                    $result->method('fetchOne')->willReturn('5');
                } else {
                    self::assertStringContainsString('ORDER BY created_at, id LIMIT 2 OFFSET 4', $sql);
                    $result->method('fetchAllAssociative')->willReturn([$this->row()]);
                }

                return $result;
            }
        );

        $page = $this->roles->getAll(new Pagination(3, 2));

        self::assertSame(5, $page->totalRecords());
        $records = iterator_to_array($page->records());
        self::assertCount(1, $records);
        self::assertTrue($this->role->hasSameState($records[0]));
    }

    /**
     * Allows protected membership only for the managed Super Admin role
     */
    public function testRegistrationWritesAuthoritativeMembershipAfterBothIdentityClaims(): void
    {
        $this->allowReferenceWrites();
        $role = Role::defineManaged(
            $this->role->getId(),
            RoleName::fromString(Role::SUPER_ADMIN_NAME),
            [$this->permission],
            $this->at
        );
        $this->connection->expects(self::once())->method('fetchFirstColumn')->with(
            self::stringContains('FROM permissions'),
            [[$this->permission->toString()], true]
        )->willReturn([$this->permission->toString()]);
        $writes = [];
        $this->connection->expects(self::exactly(2))->method('executeStatement')->willReturnCallback(
            function (string $sql, array $parameters) use ($role, &$writes): int {
                if (str_contains($sql, 'role_incarnations')) {
                    $incarnation = explode(':', $role->getRevision()->toString())[0];
                    self::assertSame([$incarnation, $role->getId()->toString()], $parameters);
                    $writes[] = 'identity';
                } else {
                    self::assertSame($this->row($role), $parameters);
                    $writes[] = 'role';
                }

                return 1;
            }
        );
        $this->connection->expects(self::once())->method('insert')->with('role_permissions', [
            'role_id' => $role->getId()->toString(), 'permission_id' => $this->permission->toString()
        ])->willReturnCallback(static function () use (&$writes): int {
            self::assertSame(['identity', 'role'], $writes);

            return 1;
        });

        $this->roles->add($role);
    }

    /**
     * Never writes memberships for unavailable permissions or a rejected role claim
     */
    #[DataProvider('rejections')]
    public function testRegistrationRejectsWithoutWritingMembership(bool $eligible, int $failedWrite): void
    {
        $this->allowReferenceWrites();
        $this->connection->expects(self::once())->method('fetchFirstColumn')->with(
            self::anything(),
            [[$this->permission->toString()], false]
        )->willReturn($eligible ? [$this->permission->toString()] : []);
        $writes = 0;
        $this->connection->expects(self::exactly($failedWrite))->method('executeStatement')->willReturnCallback(
            static function () use (&$writes, $failedWrite): int {
                return ++$writes === $failedWrite ? 0 : 1;
            }
        );
        $this->connection->expects(self::never())->method('insert');

        $this->expectException(PersistenceConflict::class);
        $this->expectExceptionMessage($eligible ? 'already in use' : 'not authoritative');
        $this->roles->add($this->role);
    }

    /**
     * Supplies rejected registration boundaries
     *
     * @return iterable<string, array{bool, int}>
     */
    public static function rejections(): iterable
    {
        yield 'ineligible permission' => [false, 0];
        yield 'incarnation rejected' => [true, 1];
        yield 'role rejected' => [true, 2];
    }

    /**
     * Refuses a stale membership snapshot and changes membership only after a successful role update
     */
    #[DataProvider('replacements')]
    public function testReplacementChecksSnapshotBeforeUpdatingMembership(bool $membershipCurrent, int $updated): void
    {
        $this->allowReferenceWrites();
        $replacement = $this->role->renameCustom(
            RoleName::fromString('ROLE_RENAMED'),
            $this->at->modify('+1 second')
        );
        $this->connection->method('fetchFirstColumn')->willReturn([$this->permission->toString()]);
        $this->connection->method('executeQuery')->willReturnCallback(
            function (string $sql) use ($membershipCurrent): Result {
                if (str_contains($sql, 'FROM role_permissions')) {
                    return $this->membership($membershipCurrent);
                }
                $result = $this->createStub(Result::class);
                if (str_contains($sql, 'FROM roles')) {
                    $result->method('fetchAssociative')->willReturn($this->row());
                }

                return $result;
            }
        );
        $statement = $this->connection->expects(self::exactly($membershipCurrent ? 1 : 0))->method('executeStatement');
        if ($membershipCurrent) {
            $statement->willReturnCallback(
                static function (string $sql, array $parameters) use ($replacement, $updated): int {
                    self::assertSame('ROLE_SAMPLE', $parameters['expected_name']);
                    self::assertSame('ROLE_RENAMED', $parameters['name']);
                    self::assertSame($replacement->getRevision()->toString(), $parameters['revision']);

                    return $updated;
                }
            );
        }
        $success = $membershipCurrent && $updated === 1;
        $this->connection->expects(self::exactly($success ? 1 : 0))->method('delete')->with(
            'role_permissions',
            ['role_id' => $this->role->getId()->toString()]
        )->willReturn(1);
        $this->connection->expects(self::exactly($success ? 1 : 0))->method('insert')->with('role_permissions', [
            'role_id' => $this->role->getId()->toString(), 'permission_id' => $this->permission->toString()
        ])->willReturn(1);

        self::assertSame($success, $this->roles->replace($this->role, $replacement));
    }

    /**
     * Supplies checked and stale write outcomes
     *
     * @return iterable<string, array{bool, int}>
     */
    public static function replacements(): iterable
    {
        yield 'stale membership' => [false, 0];
        yield 'update refused' => [true, 0];
        yield 'update accepted' => [true, 1];
    }

    /**
     * Records a role without requiring or creating empty permission membership rows
     */
    public function testRegistrationAllowsAnEmptyPermissionSet(): void
    {
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_EMPTY'), [], $this->at);
        $this->allowReferenceWrites();
        $this->connection->expects(self::never())->method('fetchFirstColumn');
        $this->connection->expects(self::never())->method('insert');
        $this->connection->expects(self::exactly(2))->method('executeStatement')->willReturn(1);

        $this->roles->add($role);
    }

    /**
     * Does not mistake a membership-only change for a no-op or write an ineligible permission
     */
    public function testMembershipOnlyReplacementChecksAllPermissionsBeforeWriting(): void
    {
        $additional = PermissionId::generate();
        $replacement = $this->role->grantPermissionToCustom($additional, $this->at->modify('+1 second'));
        $ids = [$additional->toString(), $this->permission->toString()];
        sort($ids);
        $this->allowReferenceWrites();
        $this->connection->expects(self::once())->method('fetchFirstColumn')->with(
            self::stringContains('FROM permissions'),
            [$ids, false]
        )->willReturn([$this->permission->toString()]);
        $this->connection->expects(self::never())->method('executeStatement');

        self::assertFalse($this->roles->replace($this->role, $replacement));
    }

    /**
     * Refuses an outdated revision even when the stored identity and display name agree
     */
    public function testReplacementRejectsADifferentStoredRoleRevision(): void
    {
        $at = $this->at->modify('+1 second');
        $replacement = $this->role->renameCustom(RoleName::fromString('ROLE_CHANGED'), $at);
        $this->allowReferenceWrites();
        $this->connection->method('fetchFirstColumn')->willReturn([$this->permission->toString()]);
        $this->connection->method('executeQuery')->willReturnCallback(function (string $sql): Result {
            $result = $this->createStub(Result::class);
            if (str_contains($sql, 'FROM roles')) {
                $row = $this->row();
                $row['revision'] = $this->role->getRevision()->next()->toString();
                $result->method('fetchAssociative')->willReturn($row);
            }
            self::assertStringNotContainsString('FROM role_permissions', $sql);

            return $result;
        });
        $this->connection->expects(self::never())->method('executeStatement');

        self::assertFalse($this->roles->replace($this->role, $replacement));
    }

    /**
     * Rejects an identity change before any attempt to update stored state
     */
    public function testReplacementDoesNotTransferRoleIdentity(): void
    {
        $other = Role::define(RoleId::generate(), $this->role->getName(), [$this->permission], $this->at);
        $this->connection->expects(self::never())->method('executeQuery');
        $this->connection->expects(self::never())->method('executeStatement');

        self::assertFalse($this->roles->replace($this->role, $other));
    }

    /**
     * Revalidates current membership before treating a replay as a successful no-op
     */
    #[DataProvider('snapshotOutcomes')]
    public function testNoOpRequiresCurrentMembership(bool $current): void
    {
        $this->expectRoleSnapshot($current);
        $this->connection->expects(self::never())->method('executeStatement');

        self::assertSame($current, $this->roles->replace($this->role, $this->role));
    }

    /**
     * Does not remove a stale role or report success if assignments prevent deletion
     */
    #[DataProvider('replacements')]
    public function testRemovalRequiresCurrentMembershipAndSuccessfulGuardedDelete(bool $current, int $deleted): void
    {
        $this->expectRoleSnapshot($current);
        $statement = $this->connection->expects(self::exactly($current ? 1 : 0))->method('executeStatement');
        if ($current) {
            $statement->with(self::stringContains('NOT EXISTS (SELECT 1 FROM user_role_assignments'), $this->row())
                ->willReturn($deleted);
        }

        self::assertSame($current && $deleted === 1, $this->roles->remove($this->role));
    }

    /**
     * Distinguishes a protected reference from authority to grant it to a custom role
     */
    public function testProtectedPermissionCanBeReferencedButNotGrantedToCustomRole(): void
    {
        $permission = Permission::defineManaged(
            $this->permission,
            PermissionName::fromString('MANAGE_AUTHORITY'),
            PermissionTier::SUPER_ADMIN_ONLY,
            $this->at
        );
        $this->connection->expects(self::once())->method('fetchFirstColumn')->with(
            self::stringContains('FROM permissions'),
            [[$this->permission->toString()], true]
        )->willReturn([$this->permission->toString()]);
        $this->connection->expects(self::exactly(2))->method('fetchOne')->with('SHOW transaction_isolation')
            ->willReturn('read committed');

        self::assertTrue($this->roles->validatePermissionReference($this->permission));
        self::assertFalse($this->roles->validateCustomPermissionGrant($permission));
    }

    /**
     * Requires the complete current permission snapshot to grant an eligible permission
     */
    #[DataProvider('snapshotOutcomes')]
    public function testCustomGrantRequiresCurrentEligiblePermission(bool $current): void
    {
        $permission = Permission::define($this->permission, PermissionName::fromString('READ_SAMPLE'), $this->at);
        $this->connection->method('fetchOne')->willReturnCallback(
            function (string $sql, array $parameters) use ($current): string|int|false {
                if ($sql === 'SHOW transaction_isolation') {
                    return 'read committed';
                }
                self::assertStringContainsString("tier = 'ADMIN_SAFE'", $sql);
                self::assertSame([
                    'id'         => $this->permission->toString(),
                    'name'       => 'READ_SAMPLE',
                    'managed'    => false,
                    'created_at' => '2026-10-20 12:00:00.123456+00:00',
                    'updated_at' => '2026-10-20 12:00:00.123456+00:00'
                ], $parameters);

                return $current ? 1 : false;
            }
        );

        self::assertSame($current, $this->roles->validateCustomPermissionGrant($permission));
    }

    /**
     * Supplies current and stale authority snapshots
     *
     * @return iterable<string, array{bool}>
     */
    public static function snapshotOutcomes(): iterable
    {
        yield 'current' => [true];
        yield 'stale' => [false];
    }

    /**
     * Returns the same stored role with a current or stale permission assignment
     */
    private function expectRoleSnapshot(bool $current): void
    {
        $this->allowReferenceWrites();
        $this->connection->method('executeQuery')->willReturnCallback(
            function (string $sql) use ($current): Result {
                if (str_contains($sql, 'FROM role_permissions')) {
                    return $this->membership($current);
                }
                $result = $this->createStub(Result::class);
                if (str_contains($sql, 'FROM roles')) {
                    $result->method('fetchAssociative')->willReturn($this->row());
                }

                return $result;
            }
        );
    }

    /**
     * Reports the required isolation level without connecting to PostgreSQL
     */
    private function allowReferenceWrites(): void
    {
        $this->connection->method('fetchOne')->with('SHOW transaction_isolation')->willReturn('read committed');
    }

    /**
     * Supplies the current role record in storage form
     *
     * @return array<string, bool|string>
     */
    private function row(?Role $role = null): array
    {
        $role ??= $this->role;

        return [
            'id'         => $role->getId()->toString(),
            'name'       => $role->getName()->toString(),
            'managed'    => $role->isManaged(),
            'created_at' => $role->getCreatedAt()->format('Y-m-d H:i:s.uP'),
            'updated_at' => $role->getUpdatedAt()->format('Y-m-d H:i:s.uP'),
            'revision'   => $role->getRevision()->toString()
        ];
    }

    /**
     * Supplies the stored membership snapshot
     */
    private function membership(bool $current): Result
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchFirstColumn')->willReturn($current ? [$this->permission->toString()] : []);

        return $result;
    }
}
