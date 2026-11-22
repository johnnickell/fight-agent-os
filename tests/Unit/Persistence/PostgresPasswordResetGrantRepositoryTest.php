<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\PasswordResetGrant\PasswordResetGrantRecords;
use App\Adapter\Persistence\AccessControl\PasswordResetGrant\PostgresPasswordResetGrantRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PgSqlException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant;
use Fight\AccessControl\Domain\AccessControl\User\RecoveryAuthority;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects reset-grant generations and guarded delivery transitions at the database boundary
 */
final class PostgresPasswordResetGrantRepositoryTest extends TestCase
{
    /**
     * Rejects reset writes without the transaction isolation needed to see the authoritative state
     */
    #[DataProvider('unsafeTransactions')]
    public function testWriteRequiresReadCommitted(bool $active, string $message): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn($active);
        $isolation = $connection->expects(self::exactly($active ? 1 : 0))->method('fetchOne');
        if ($active) {
            $isolation->with('SHOW transaction_isolation')->willReturn('repeatable read');
        }
        $connection->expects(self::never())->method('insert');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);
        new PostgresPasswordResetGrantRepository($connection)->add($this->grant());
    }

    /**
     * Supplies transaction states where a reset write cannot proceed
     *
     * @return iterable<string, array{bool, string}>
     */
    public static function unsafeTransactions(): iterable
    {
        yield 'no transaction' => [false, 'Password-reset persistence requires an enclosing transaction.'];
        yield 'fixed snapshot' => [true, 'Recovery writes require READ COMMITTED isolation.'];
    }

    /**
     * Requires the issuing authority to match before inserting an initial generation
     */
    #[DataProvider('initialAdmission')]
    public function testInitialGenerationRespectsAuthorityAndConstraintOutcome(bool $current, bool $conflict): void
    {
        $grant = $this->grant();
        $connection = $this->connection($grant, $current);
        $connection->method('fetchAssociative')->willReturn(false);
        $insert = $connection->expects(self::exactly($current ? 1 : 0))->method('insert');
        if ($current) {
            $insert->with('password_reset_grants', self::callback(static function (array $row) use ($grant): bool {
                self::assertSame($grant->getUserId()->toString(), $row['user_id']);
                self::assertSame('reset@example.test', $row['delivery_email']);
                self::assertSame(0, $row['generation']);
                self::assertSame(3, $row['recovery_authentication_version']);
                self::assertSame(7, $row['recovery_canonical_email_revision']);

                return true;
            }));
            if ($conflict) {
                $insert->willThrowException(new UniqueConstraintViolationException(
                    new PgSqlException('Synthetic grant collision', '23505'),
                    null
                ));
            } else {
                $insert->willReturn(1);
            }
        }
        $connection->expects(self::exactly($conflict ? 1 : 0))->method('rollbackSavepoint');

        self::assertSame($current && !$conflict, new PostgresPasswordResetGrantRepository($connection)->add($grant));
    }

    /**
     * Supplies current, changed, and concurrently claimed issuance outcomes
     *
     * @return iterable<string, array{bool, bool}>
     */
    public static function initialAdmission(): iterable
    {
        yield 'current authority' => [true, false];
        yield 'authority changed' => [false, false];
        yield 'grant constraint conflict' => [true, true];
    }

    /**
     * Persists the retry outcome using the persisted claim revision, not the replacement revision
     */
    public function testFailureOfClaimedDeliveryWritesRetryWithoutReplacingCredential(): void
    {
        $token = CredentialDeliveryClaimToken::generate();
        $now = $this->now();
        $claimed = $this->grant()->claimDelivery($token, $now, $now->modify('+5 minutes'));
        $failureAt = $now->modify('+1 minute');
        $retry = $claimed->failDelivery($token, $failureAt, CredentialDeliveryFailure::UNEXPECTED_PROVIDER);
        $stored = PasswordResetGrantRecords::fields($claimed);
        $connection = $this->connection($claimed);
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('FOR UPDATE'),
            [$claimed->getUserId()->toString()]
        )->willReturn($stored);
        $connection->expects(self::once())->method('update')->with(
            'password_reset_grants',
            self::callback(static function (array $fields): bool {
                self::assertArrayNotHasKey('credential_digest', $fields);
                self::assertSame('retry_pending', $fields['delivery_status']);
                self::assertNull($fields['delivery_claim_token']);
                self::assertSame(1, $fields['delivery_attempt_count']);
                self::assertSame('2026-10-21 12:01:00.123456+00:00', $fields['delivery_last_outcome_at']);
                self::assertSame(
                    CredentialDeliveryFailure::UNEXPECTED_PROVIDER->value,
                    $fields['delivery_last_failure']
                );
                self::assertSame(2, $fields['revision']);

                return true;
            }),
            ['id' => $claimed->getId()->toString(), 'revision' => 1]
        )->willReturn(1);

        self::assertTrue(new PostgresPasswordResetGrantRepository($connection)->replace($claimed, $retry));
    }

    /**
     * Advances the stored generation and rolls back the terminal write if the successor collides
     */
    public function testSuccessorConflictRollsBackRevocation(): void
    {
        $grant = $this->grant();
        $terminal = $grant->revoke($this->now()->modify('+1 minute'));
        $successor = $this->grant($grant->getUserId());
        $connection = $this->connection($successor);
        $connection->method('fetchAssociative')->willReturn(PasswordResetGrantRecords::fields($grant) + [
            'generation' => '4'
        ]);
        $connection->expects(self::once())->method('update')->with(
            'password_reset_grants',
            self::callback(static fn (array $fields): bool => $fields['delivery_ciphertext'] === null
                && $fields['revoked_at'] === '2026-10-21 12:01:00.123456+00:00'),
            ['id' => $grant->getId()->toString(), 'revision' => 0]
        )->willReturn(1);
        $connection->expects(self::once())->method('insert')->with(
            'password_reset_grants',
            self::callback(static fn (array $fields): bool => $fields['generation'] === 5
                && $fields['id'] === $successor->getId()->toString())
        )->willThrowException(new UniqueConstraintViolationException(
            new PgSqlException('Synthetic successor collision', '23505'),
            null
        ));
        $connection->expects(self::once())->method('rollbackSavepoint');

        self::assertFalse(
            new PostgresPasswordResetGrantRepository($connection)->replaceWithSuccessor($grant, $terminal, $successor)
        );
    }

    /**
     * Appends only behind the persisted terminal grant, without rewriting older generations
     */
    #[DataProvider('terminalMatches')]
    public function testTerminalAppendRequiresTheLatestSnapshot(bool $matches): void
    {
        $grant = $this->grant();
        $terminal = $grant->revoke($this->now()->modify('+1 minute'));
        $successor = $this->grant($grant->getUserId());
        $connection = $this->connection($successor);
        $connection->method('fetchAssociative')->willReturn(
            PasswordResetGrantRecords::fields($matches ? $terminal : $grant) + ['generation' => '4']
        );
        $connection->expects(self::never())->method('update');
        $insert = $connection->expects(self::exactly($matches ? 1 : 0))->method('insert');
        if ($matches) {
            $insert->with('password_reset_grants', self::callback(static fn (array $fields): bool =>
                $fields['id'] === $successor->getId()->toString() && $fields['generation'] === 5))->willReturn(1);
        }

        self::assertSame(
            $matches,
            new PostgresPasswordResetGrantRepository($connection)->appendAfterTerminal($terminal, $successor)
        );
    }

    /**
     * Supplies current and stale terminal snapshots
     *
     * @return iterable<string, array{bool}>
     */
    public static function terminalMatches(): iterable
    {
        yield 'persisted terminal revision' => [true];
        yield 'persisted issued revision' => [false];
    }

    /**
     * Erases expired delivery material even after the account authority changes
     */
    public function testExpiryCleanupDoesNotRequireCurrentLoginAuthority(): void
    {
        $grant = $this->grant();
        $expired = $grant->expireDeliveryAt($this->now()->modify('+1 hour'));
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('fetchOne')->with('SHOW transaction_isolation')
            ->willReturn('read committed');
        $connection->method('fetchAssociative')->willReturn(PasswordResetGrantRecords::fields($grant));
        $connection->expects(self::once())->method('update')->with(
            'password_reset_grants',
            self::callback(static fn (array $fields): bool => $fields['delivery_status'] === 'expired'
                && $fields['delivery_ciphertext'] === null),
            ['id' => $grant->getId()->toString(), 'revision' => 0]
        )->willReturn(1);

        self::assertTrue(new PostgresPasswordResetGrantRepository($connection)->replace($grant, $expired));
    }

    /**
     * Restores terminal state and revision from a delivery lookup without restoring erased material
     */
    public function testDeliveryLookupHydratesTerminalGrant(): void
    {
        $grant = $this->grant();
        $deliveryId = $grant->getDelivery()->getId();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('WHERE delivery_id = ?'),
            [$deliveryId->toString()]
        )->willReturn(array_replace(PasswordResetGrantRecords::fields($grant), [
            'consumed_at'         => '2026-10-21 12:01:00.123456+00:00',
            'delivery_ciphertext' => null,
            'delivery_status'     => 'invalidated',
            'revision'            => '2'
        ]));

        $restored = new PostgresPasswordResetGrantRepository($connection)->getByDeliveryId($deliveryId);

        self::assertNotNull($restored);
        self::assertTrue($grant->getId()->equals($restored->getId()));
        self::assertTrue($deliveryId->equals($restored->getDelivery()->getId()));
        self::assertTrue($restored->isConsumed());
        self::assertEquals($this->now()->modify('+1 minute'), $restored->getConsumedAt());
        self::assertSame(2, $restored->getRevision());
        self::assertFalse($restored->getDelivery()->hasRecoverableMaterial());
    }

    /**
     * Uses lease expiry as the next attempt time when a due row is already claimed
     */
    public function testDueProjectionUsesLeaseExpiryAndStoredRevision(): void
    {
        $now = $this->now();
        $lease = $now->modify('+5 minutes');
        $claimed = $this->grant()->claimDelivery(CredentialDeliveryClaimToken::generate(), $now, $lease);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')->with(
            self::stringContains('g.delivery_lease_until <= ?'),
            array_fill(0, 3, '2026-10-21 12:05:00.123456+00:00') + [3 => 4]
        )->willReturn([PasswordResetGrantRecords::fields($claimed)]);

        $repository = new PostgresPasswordResetGrantRepository($connection);
        self::assertSame([], $repository->findDue($lease, 0));
        $due = $repository->findDue($lease, 4);

        self::assertCount(1, $due);
        self::assertTrue($claimed->getDelivery()->getId()->equals($due[0]->getDeliveryId()));
        self::assertSame(1, $due[0]->getRevision());
        self::assertEquals($lease, $due[0]->getDueAt());
    }

    /**
     * Releases expired material even after the owner loses recovery authority
     */
    public function testExpiredClaimCanDiscardMaterialAfterAuthorityChanges(): void
    {
        $grant = $this->grant();
        $expiry = $grant->getExpiresAt();
        $token = CredentialDeliveryClaimToken::generate();
        $claimed = $grant->claimDelivery($token, $expiry->modify('-2 minutes'), $expiry);
        $failureAt = $expiry->modify('-1 second');
        $expired = $claimed->failDelivery($token, $failureAt, CredentialDeliveryFailure::UNEXPECTED_PROVIDER);
        $connection = $this->connection($claimed, current: false);
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('FOR UPDATE'),
            [$claimed->getUserId()->toString()]
        )->willReturn(PasswordResetGrantRecords::fields($claimed));
        $connection->expects(self::once())->method('update')->with(
            'password_reset_grants',
            self::callback(static function (array $fields) use ($failureAt): bool {
                self::assertSame('expired', $fields['delivery_status']);
                self::assertSame($failureAt->format('Y-m-d H:i:s.uP'), $fields['delivery_last_outcome_at']);
                self::assertSame(
                    CredentialDeliveryFailure::UNEXPECTED_PROVIDER->value,
                    $fields['delivery_last_failure']
                );
                self::assertNull($fields['delivery_ciphertext']);
                self::assertNull($fields['delivery_claim_token']);

                return true;
            }),
            ['id' => $grant->getId()->toString(), 'revision' => $claimed->getRevision()]
        )->willReturn(1);

        self::assertTrue(new PostgresPasswordResetGrantRepository($connection)->replace($claimed, $expired));
    }

    /**
     * Does not persist a delivery claim if its issuing authority or grant snapshot is stale
     */
    #[DataProvider('claimAdmission')]
    public function testClaimRequiresCurrentGrantAndAuthority(bool $snapshot, bool $authority): void
    {
        $grant = $this->grant();
        $token = CredentialDeliveryClaimToken::generate();
        $lease = $this->now()->modify('+5 minutes');
        $claim = $grant->claimDelivery($token, $this->now(), $lease);
        $connection = $this->connection($grant, $authority);
        $stored = $snapshot ? $grant : $grant->revoke($this->now());
        $connection->method('fetchAssociative')->willReturn(PasswordResetGrantRecords::fields($stored));
        $admitted = $snapshot && $authority;
        $update = $connection->expects(self::exactly((int) $admitted))->method('update');
        if ($admitted) {
            $update->with(
                'password_reset_grants',
                self::callback(static fn (array $fields): bool => $fields['delivery_status'] === 'claimed'
                    && $fields['delivery_claim_token'] === $token->toString()
                    && $fields['delivery_lease_until'] === $lease->format('Y-m-d H:i:s.uP')),
                ['id' => $grant->getId()->toString(), 'revision' => $grant->getRevision()]
            )->willReturn(1);
        }

        self::assertSame($admitted, new PostgresPasswordResetGrantRepository($connection)->replace($grant, $claim));
    }

    /**
     * Supplies independent authority and revision outcomes for claiming delivery
     *
     * @return iterable<string, array{bool, bool}>
     */
    public static function claimAdmission(): iterable
    {
        yield 'both are current' => [true, true];
        yield 'grant revoked' => [false, true];
        yield 'authority changed' => [true, false];
    }

    /**
     * Reads stored grants by distinct grant and owner identities
     */
    #[DataProvider('lookupCases')]
    public function testReadsGrantAndOwnerIdentities(bool $byOwner, bool $exists): void
    {
        $grant = $this->grant();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains($byOwner ? 'user_id = ? ORDER BY generation DESC' : 'WHERE id = ?'),
            [$byOwner ? $grant->getUserId()->toString() : $grant->getId()->toString()]
        )->willReturn($exists ? PasswordResetGrantRecords::fields($grant) : false);
        $repository = new PostgresPasswordResetGrantRepository($connection);

        if ($byOwner) {
            $stored = $repository->getLatestByUserId($grant->getUserId());
        } else {
            $stored = $repository->getById($grant->getId());
        }

        self::assertSame($exists ? $grant->getId()->toString() : null, $stored?->getId()->toString());
    }

    /**
     * Supplies present and absent grant and owner results
     *
     * @return iterable<string, array{bool, bool}>
     */
    public static function lookupCases(): iterable
    {
        yield 'grant exists' => [false, true];
        yield 'grant absent' => [false, false];
        yield 'owner has a grant' => [true, true];
        yield 'owner has no grant' => [true, false];
    }

    /**
     * Selects only reset material that can still be erased and returns a reset delivery reference
     */
    public function testExpiryProjectionSelectsRecoverableMaterial(): void
    {
        $grant = $this->grant();
        $deadline = '2026-10-21 13:00:00.123456+00:00';
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')->with(
            self::callback(static fn (string $sql): bool => str_contains($sql, 'FROM password_reset_grants')
                && str_contains($sql, 'delivery_ciphertext IS NOT NULL')),
            [$deadline, 2]
        )->willReturn([PasswordResetGrantRecords::fields($grant)]);

        $expired = new PostgresPasswordResetGrantRepository($connection)->findExpired($grant->getExpiresAt(), 2);

        self::assertCount(1, $expired);
        self::assertSame('password_reset', $expired[0]->getPurpose());
        self::assertSame($grant->getDelivery()->getId()->toString(), $expired[0]->getDeliveryId()->toString());
        self::assertNull($expired[0]->getEmailChangeGrantId());
        self::assertSame(CredentialDeliveryStatus::PENDING, $expired[0]->getStatus());
        self::assertEquals($grant->getExpiresAt(), $expired[0]->getExpiresAt());
    }

    /**
     * Keeps material on a requeued attempt and clears it on acknowledgment, failure or invalidation
     */
    #[DataProvider('deliveryTransitions')]
    public function testDeliveryWritesRespectMaterialLifetime(string $status, bool $retainsMaterial): void
    {
        $grant = $this->grant();
        $now = $this->now();
        $token = CredentialDeliveryClaimToken::generate();
        $claimed = $grant->claimDelivery($token, $now, $now->modify('+5 minutes'));
        $retry = $claimed->failDelivery($token, $now, CredentialDeliveryFailure::UNEXPECTED_PROVIDER);
        [$before, $after] = match ($status) {
            'pending' => [$retry, $retry->requestDeliveryRetry()],
            'delivered' => [$claimed, $claimed->confirmDelivery($token, $now)],
            'permanent_failure' => [$claimed, $claimed->failDeliveryPermanently($token, $now)],
            'invalidated' => [$grant, $grant->invalidateDelivery()],
            default => throw new LogicException('Unknown reset delivery fixture.')
        };
        $connection = $this->connection($grant, current: $status !== 'invalidated');
        $connection->expects(self::once())->method('fetchAssociative')
            ->willReturn(PasswordResetGrantRecords::fields($before));
        $connection->expects(self::once())->method('update')->with(
            'password_reset_grants',
            self::callback(static function (array $fields) use ($status, $retainsMaterial): bool {
                self::assertSame($status, $fields['delivery_status']);
                self::assertNull($fields['delivery_claim_token']);
                $material = $retainsMaterial ? 'synthetic-encrypted-material' : null;
                self::assertSame($material, $fields['delivery_ciphertext']);

                return true;
            }),
            ['id' => $before->getId()->toString(), 'revision' => $before->getRevision()]
        )->willReturn(1);

        self::assertTrue(new PostgresPasswordResetGrantRepository($connection)->replace($before, $after));
    }

    /**
     * Supplies retry and terminal delivery writes
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function deliveryTransitions(): iterable
    {
        yield 'operator retry' => ['pending', true];
        yield 'provider acknowledgment' => ['delivered', false];
        yield 'provider terminal failure' => ['permanent_failure', false];
        yield 'authority-invalidated delivery' => ['invalidated', false];
    }

    /**
     * Answers the issuing authority check without opening a database connection
     */
    private function connection(PasswordResetGrant $grant, bool $current = true): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('fetchOne')->willReturnCallback(
            static function (string $sql, array $parameters = []) use ($grant, $current): string|int|false {
                if ($sql === 'SHOW transaction_isolation') {
                    return 'read committed';
                }
                self::assertStringContainsString("state = 'active'", $sql);
                self::assertSame([$grant->getUserId()->toString(), 3, 7, 'reset@example.test'], $parameters);

                return $current ? 1 : false;
            }
        );

        return $connection;
    }

    /**
     * Issues synthetic material for an identity with independent authority revisions
     */
    private function grant(?UserId $userId = null): PasswordResetGrant
    {
        $now = $this->now();

        return PasswordResetGrant::issue(
            $userId ?? UserId::generate(),
            PasswordResetCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 hour'),
            EmailAddress::fromString('Reset@Example.test'),
            'synthetic-encrypted-material',
            new RecoveryAuthority(3, 7)
        );
    }

    /**
     * Supplies a timestamp whose microseconds must survive persistence
     */
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-21T12:00:00.123456Z');
    }
}
