<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\Agent\PostgresAgentRepository;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\Agent\Agent;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentCredentialId;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentId;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentName;
use Fight\AccessControl\Domain\AccessControl\Agent\Exception\AgentUpdateException;
use Fight\AccessControl\Domain\AccessControl\AgentCredentialOperation\AgentOperationFailure;
use Fight\AccessControl\Domain\AccessControl\AgentCredentialOperation\Exception\AgentOperationRejectedException;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\Common\Domain\Repository\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects persisted Agent authority and its guarded transitions
 */
final class PostgresAgentRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private PostgresAgentRepository $repository;
    private Agent $agent;
    private PermissionId $permission;
    private DateTimeImmutable $at;
    private DateTimeImmutable $later;

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
        $this->repository = new PostgresAgentRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection)
        );
        $this->at = new DateTimeImmutable('2026-10-20T12:00:00.123456Z');
        $this->later = $this->at->modify('+1 second');
        $this->permission = PermissionId::generate();
        $this->agent = Agent::provision(
            AgentId::generate(),
            AgentName::fromString('sample-agent'),
            AgentCredentialId::generate(),
            'opaque-fixture-envelope',
            $this->at
        );
    }

    /**
     * Does not advertise an operation contract from persistence alone
     */
    public function testCredentialOperationContractIsUnavailable(): void
    {
        try {
            $this->repository->getOperationContract();
        } catch (AgentOperationRejectedException $failure) {
            self::assertSame(AgentOperationFailure::UNAVAILABLE, $failure->getReason());

            return;
        }
        self::fail('Storage alone cannot admit credential operations');
    }

    /**
     * Selects each identity correctly and restores direct grants and credential state
     */
    public function testIdentityReadsHydrateAuthorityAndReportMissingRecords(): void
    {
        $granted = $this->agent->grantPermission($this->permission, $this->later);
        $this->connection->expects(self::exactly(2))->method('fetchAssociative')->willReturnCallback(
            function (string $sql, array $parameters) use ($granted): array|false {
                if (str_contains($sql, 'credential_id')) {
                    self::assertSame([$granted->getCredentialId()->toString()], $parameters);

                    return $this->row($granted);
                }
                self::assertSame('SELECT * FROM agents WHERE id = ?', $sql);
                self::assertSame([$granted->getId()->toString()], $parameters);

                return false;
            }
        );
        $this->expectAssignments($granted);

        self::assertNull($this->repository->getById($granted->getId()));
        self::assertEquals($granted, $this->repository->getByCredentialId($granted->getCredentialId()));
    }

    /**
     * Counts matching Agent rows separately from the ordered page
     */
    public function testPageRetainsFullCountAndHydratesItsRows(): void
    {
        $this->connection->expects(self::exactly(2))->method('executeQuery')->willReturnCallback(
            function (string $sql): Result {
                $result = $this->createStub(Result::class);
                if (str_contains($sql, 'COUNT(*)')) {
                    self::assertStringNotContainsString('LIMIT', $sql);
                    $result->method('fetchOne')->willReturn('8');
                } else {
                    self::assertStringContainsString('ORDER BY created_at, id LIMIT 2 OFFSET 4', $sql);
                    $result->method('fetchAllAssociative')->willReturn([$this->row($this->agent)]);
                }

                return $result;
            }
        );
        $this->expectAssignments($this->agent);

        $page = $this->repository->getAll(new Pagination(3, 2));

        self::assertSame(8, $page->totalRecords());
        self::assertEquals([$this->agent], iterator_to_array($page->records()));
    }

    /**
     * Reads direct Agent assignments by Permission identity
     */
    public function testAssignmentLookupDistinguishesMissingAndPresent(): void
    {
        $this->connection->expects(self::exactly(2))->method('fetchOne')->with(
            self::stringContains('FROM agent_permission_assignments WHERE permission_id = ?'),
            [$this->permission->toString()]
        )->willReturnOnConsecutiveCalls(false, '1');

        self::assertFalse($this->repository->hasPermissionAssignment($this->permission));
        self::assertTrue($this->repository->hasPermissionAssignment($this->permission));
    }

    /**
     * Reports an identity or credential collision without silently accepting the same Agent twice
     */
    public function testAddWritesOnlyNewAgentAuthority(): void
    {
        $this->expectFence(2);
        $this->connection->expects(self::exactly(2))->method('executeStatement')->with(
            self::stringContains('ON CONFLICT DO NOTHING'),
            $this->row($this->agent)
        )->willReturnOnConsecutiveCalls(1, 0);

        $this->repository->add($this->agent);
        $this->expectException(PersistenceConflict::class);
        $this->expectExceptionMessage('already in use');
        $this->repository->add($this->agent);
    }

    /**
     * Refuses to insert a previously changed Agent as new authority
     */
    public function testAddRejectsExistingAuthorityBeforeWriting(): void
    {
        $this->expectFence();
        $this->connection->expects(self::never())->method('executeStatement');

        $this->expectException(PersistenceConflict::class);
        $this->expectExceptionMessage('Only new Agent authority');
        $this->repository->add($this->agent->grantPermission($this->permission, $this->later));
    }

    /**
     * Writes only a changed name on the locked Agent and returns its update time
     */
    public function testRenameWritesOnlyTheNameAndTimestamp(): void
    {
        $this->expectFence(2);
        $this->expectLocked($this->agent, 2);
        $this->expectAssignments($this->agent, 2);
        $this->connection->expects(self::once())->method('executeStatement')->with(
            'UPDATE agents SET name = ?, updated_at = ? WHERE id = ?',
            ['renamed-agent', $this->later->format('Y-m-d H:i:s.uP'), $this->agent->getId()->toString()]
        )->willReturn(1);

        self::assertNull($this->repository->rename(
            $this->agent->getId(),
            $this->agent->getName(),
            fn(): DateTimeImmutable => $this->later
        ));
        self::assertEquals($this->later, $this->repository->rename(
            $this->agent->getId(),
            AgentName::fromString('renamed-agent'),
            fn() => $this->later
        ));
    }

    /**
     * Does not obtain an update time or write when the Agent is unavailable
     */
    public function testRenameRejectsMissingAgent(): void
    {
        $this->expectFence();
        $this->expectLocked(null);
        $this->connection->expects(self::never())->method('executeStatement');

        $this->expectException(AgentUpdateException::class);
        $this->repository->rename(
            $this->agent->getId(),
            AgentName::fromString('renamed-agent'),
            static fn() => self::fail('An unavailable Agent cannot be renamed')
        );
    }

    /**
     * Writes only an admissible credential successor of the locked current snapshot
     */
    public function testCredentialReplacementRejectsStaleAndUnrelatedSuccessors(): void
    {
        $this->expectFence(3);
        $this->expectLocked($this->agent, 3);
        $this->expectAssignments($this->agent, 3);
        $rotated = $this->agent->rotateRecoverableCredential(
            $this->agent->getCredentialId(),
            $this->agent->getCredentialRevision(),
            AgentCredentialId::generate(),
            'opaque-successor-envelope',
            $this->later
        );
        $this->connection->expects(self::once())->method('executeStatement')->with(
            self::stringContains('UPDATE agents SET state = :state, credential_id = :credential_id'),
            $this->row($rotated)
        )->willReturn(1);

        self::assertFalse($this->repository->replace($rotated, $rotated->revoke($this->later->modify('+1 second'))));
        self::assertFalse($this->repository->replace($this->agent, $this->agent->grantPermission(
            $this->permission,
            $this->later
        )));
        self::assertTrue($this->repository->replace($this->agent, $rotated));
    }

    /**
     * Confirms that the entire Permission snapshot is current and safe for direct grants
     */
    #[DataProvider('permissionSnapshots')]
    public function testPermissionAdmissionChecksPersistedAuthority(string $state, bool $expected): void
    {
        $this->expectFence();
        $permission = Permission::defineManaged(
            $this->permission,
            PermissionName::fromString('SAMPLE_READ'),
            $state === 'protected' ? PermissionTier::SUPER_ADMIN_ONLY : PermissionTier::ADMIN_SAFE,
            $this->at
        );
        $this->connection->expects(self::once())->method('fetchAssociative')->with(
            'SELECT * FROM permissions WHERE id = ?',
            [$this->permission->toString()]
        )->willReturn($state === 'missing' ? false : [
            'name'       => $permission->getName()->toString(),
            'managed'    => true,
            'tier'       => $permission->getTier()->value,
            'created_at' => $this->at->format('Y-m-d H:i:s.uP'),
            'updated_at' => ($state === 'stale' ? $this->later : $this->at)->format('Y-m-d H:i:s.uP')
        ]);

        self::assertSame($expected, $this->repository->validatePermissionAssignments([$permission]));
    }

    /**
     * Supplies Permission admission outcomes
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function permissionSnapshots(): iterable
    {
        yield 'current and safe' => ['current', true];
        yield 'changed after loading' => ['stale', false];
        yield 'protected tier' => ['protected', false];
        yield 'deleted' => ['missing', false];
    }

    /**
     * Updates direct memberships and their revision only if the Permission tier remains safe
     */
    #[DataProvider('tierAdmission')]
    public function testAssignmentReplacementChecksTierBeforeChangingMembership(bool $safe): void
    {
        $this->expectFence(stubIsolation: false);
        $this->expectLocked($this->agent);
        $this->expectAssignments($this->agent);
        $this->connection->expects(self::exactly(2))->method('fetchOne')->willReturnCallback(
            function (string $sql, array $parameters) use ($safe): string|false {
                if ($sql === 'SHOW transaction_isolation') {
                    return 'read committed';
                }
                self::assertStringContainsString('FROM permissions WHERE id = ? AND tier = ?', $sql);
                self::assertSame([$this->permission->toString(), PermissionTier::ADMIN_SAFE->value], $parameters);

                return $safe ? '1' : false;
            }
        );
        $id = $this->agent->getId()->toString();
        $successor = $this->agent->grantPermission($this->permission, $this->later);
        $writes = [];
        if ($safe) {
            $this->connection->expects(self::exactly(2))->method('executeStatement')->willReturnCallback(
                function (string $sql, array $parameters) use ($id, $successor, &$writes): int {
                    if ($writes === []) {
                        self::assertSame('DELETE FROM agent_permission_assignments WHERE agent_id = ?', $sql);
                        self::assertSame([$id], $parameters);
                        $writes[] = 'clear';
                    } else {
                        self::assertSame(['clear', 'insert'], $writes);
                        self::assertStringContainsString('UPDATE agents SET permission_assignment_revision', $sql);
                        self::assertSame([
                            $successor->getPermissionAssignmentRevision(), $this->later->format('Y-m-d H:i:s.uP'), $id
                        ], $parameters);
                        $writes[] = 'revision';
                    }

                    return 1;
                }
            );
            $this->connection->expects(self::once())->method('insert')->with('agent_permission_assignments', [
                'agent_id' => $id, 'permission_id' => $this->permission->toString()
            ])->willReturnCallback(static function () use (&$writes): int {
                $writes[] = 'insert';

                return 1;
            });
        } else {
            $this->connection->expects(self::never())->method('executeStatement');
            $this->connection->expects(self::never())->method('insert');
        }

        self::assertSame($safe, $this->repository->replacePermissionAssignments($this->agent, $successor));
    }

    /**
     * Supplies the two tier decisions at the point of change
     *
     * @return iterable<string, array{bool}>
     */
    public static function tierAdmission(): iterable
    {
        yield 'admin safe' => [true];
        yield 'no longer safe' => [false];
    }

    /**
     * Leaves existing grants untouched when the locked Agent has newer membership authority
     */
    public function testAssignmentReplacementRejectsAStaleSnapshot(): void
    {
        $this->expectFence();
        $current = $this->agent->grantPermission($this->permission, $this->later);
        $this->expectLocked($current);
        $this->expectAssignments($current);
        $this->connection->expects(self::never())->method('executeStatement');
        $this->connection->expects(self::never())->method('insert');

        self::assertFalse($this->repository->replacePermissionAssignments($this->agent, $current));
    }

    /**
     * Expects the transaction to hold the shared Permission reference fence
     */
    private function expectFence(int $count = 1, bool $stubIsolation = true): void
    {
        $this->connection->expects(self::exactly($count))->method('executeQuery')->with(
            'SELECT pg_advisory_xact_lock(?, ?)',
            [24120, 1]
        )->willReturn($this->createStub(Result::class));
        if ($stubIsolation) {
            $this->connection->method('fetchOne')->with('SHOW transaction_isolation')->willReturn('read committed');
        }
    }

    /**
     * Supplies the current Agent row under a write lock
     */
    private function expectLocked(?Agent $agent, int $count = 1): void
    {
        $this->connection->expects(self::exactly($count))->method('fetchAssociative')->with(
            'SELECT * FROM agents WHERE id = ? FOR UPDATE',
            [$this->agent->getId()->toString()]
        )->willReturn($agent === null ? false : $this->row($agent));
    }

    /**
     * Supplies direct grants scoped to the Agent identity
     */
    private function expectAssignments(Agent $agent, int $count = 1): void
    {
        $this->connection->expects(self::exactly($count))->method('fetchFirstColumn')->with(
            self::stringContains('FROM agent_permission_assignments WHERE agent_id = ?'),
            [$agent->getId()->toString()]
        )->willReturn(array_map(static fn(PermissionId $id): string => $id->toString(), $agent->getPermissionIds()));
    }

    /**
     * Describes the stored Agent authority
     *
     * @return array<string, string|int>
     */
    private function row(Agent $agent): array
    {
        return [
            'id'                                    => $agent->getId()->toString(),
            'name'                                  => $agent->getName()->toString(),
            'state'                                 => $agent->getState()->value,
            'credential_id'                         => $agent->getCredentialId()->toString(),
            'credential_revision'                   => $agent->getCredentialRevision(),
            'encrypted_hmac_shared_secret_envelope' => $agent->getEncryptedHmacSharedSecretEnvelope(),
            'permission_assignment_revision'        => $agent->getPermissionAssignmentRevision(),
            'created_at'                            => $agent->getCreatedAt()->format('Y-m-d H:i:s.uP'),
            'updated_at'                            => $agent->getUpdatedAt()->format('Y-m-d H:i:s.uP')
        ];
    }
}
