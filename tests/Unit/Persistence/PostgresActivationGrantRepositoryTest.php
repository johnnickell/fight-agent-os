<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\ActivationGrant\ActivationGrantRecords;
use App\Adapter\Persistence\AccessControl\ActivationGrant\PostgresActivationGrantRepository;
use App\Adapter\Persistence\PersistenceConflict;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PgSqlException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects activation issuance, delivery projection and guarded generation changes
 */
final class PostgresActivationGrantRepositoryTest extends TestCase
{
    /**
     * Requires a transaction and an existing owner before looking up or writing grant generations
     *
     * @phpstan-param class-string<\Throwable> $exception
     */
    #[DataProvider('unavailableOwners')]
    public function testIssuanceRequiresOwnerInAnEnclosingTransaction(bool $inTransaction, string $exception): void
    {
        $grant = $this->grant();
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn($inTransaction);
        $owner = $connection->expects(self::exactly((int) $inTransaction))->method('fetchOne');
        if ($inTransaction) {
            $owner->with(self::stringContains('FOR KEY SHARE'), [$grant->getUserId()->toString()])
                ->willReturn(false);
        }
        $connection->expects(self::never())->method('fetchAssociative');
        $connection->expects(self::never())->method('insert');

        $this->expectException($exception);
        new PostgresActivationGrantRepository($connection)->add($grant);
    }

    /**
     * Supplies blocked issuance states
     *
     * @return iterable<string, array{bool, class-string<\Throwable>}>
     */
    public static function unavailableOwners(): iterable
    {
        yield 'outside transaction' => [false, LogicException::class];
        yield 'owner absent' => [true, PersistenceConflict::class];
    }

    /**
     * Inserts the initial generation only if there is no existing grant to supersede
     */
    #[DataProvider('storedSnapshots')]
    public function testInitialGrantCannotReplaceAnExistingGeneration(bool $exists): void
    {
        $grant = $this->grant();
        $connection = $this->connection($grant->getUserId());
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('ORDER BY generation DESC'),
            [$grant->getUserId()->toString()]
        )->willReturn($exists ? ActivationGrantRecords::fields($grant) : false);
        $insert = $connection->expects(self::exactly((int) !$exists))->method('insert');
        if (!$exists) {
            $insert->with('activation_grants', self::callback(static function (array $row) use ($grant): bool {
                self::assertSame($grant->getId()->toString(), $row['id']);
                self::assertSame('pending', $row['delivery_status']);
                self::assertSame(0, $row['generation']);
                self::assertNull($row['predecessor_id']);

                return true;
            }))->willReturn(1);
        }

        self::assertSame(!$exists, new PostgresActivationGrantRepository($connection)->add($grant));
    }

    /**
     * Supplies present and absent stored grants
     *
     * @return iterable<string, array{bool}>
     */
    public static function storedSnapshots(): iterable
    {
        yield 'stored grant' => [true];
        yield 'no stored grant' => [false];
    }

    /**
     * Writes a successor behind its terminal predecessor and never inserts it after a lost update
     */
    #[DataProvider('renewalOutcomes')]
    public function testSuccessorRequiresTerminalUpdate(int $updated, bool $duplicate): void
    {
        $grant = $this->grant();
        $terminal = $grant->revoke($this->now()->modify('+1 minute'));
        $successor = $this->grant($grant->getUserId());
        $connection = $this->connection($grant->getUserId());
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('FOR UPDATE'),
            [$grant->getUserId()->toString()]
        )->willReturn(ActivationGrantRecords::fields($grant) + ['generation' => '4']);
        $connection->expects(self::once())->method('update')->with(
            'activation_grants',
            self::callback(static function (array $fields) use ($terminal): bool {
                self::assertArrayNotHasKey('credential_digest', $fields);
                self::assertSame($terminal->getRevokedAt()?->format('Y-m-d H:i:s.uP'), $fields['revoked_at']);
                self::assertNull($fields['delivery_ciphertext']);

                return true;
            }),
            ['id' => $grant->getId()->toString(), 'revision' => $grant->getRevision()]
        )->willReturn($updated);
        $insert = $connection->expects(self::exactly($updated))->method('insert');
        if ($updated === 1) {
            $insert->with('activation_grants', self::callback(static function (array $row) use (
                $grant,
                $successor
            ): bool {
                self::assertSame($successor->getId()->toString(), $row['id']);
                self::assertSame($grant->getId()->toString(), $row['predecessor_id']);
                self::assertSame(5, $row['generation']);

                return true;
            }));
            if ($duplicate) {
                $insert->willThrowException(new UniqueConstraintViolationException(
                    new PgSqlException('Synthetic successor collision', '23505'),
                    null
                ));
            } else {
                $insert->willReturn(1);
            }
        }
        $connection->expects(self::exactly((int) $duplicate))->method('rollbackSavepoint');

        self::assertSame(
            $updated === 1 && !$duplicate,
            new PostgresActivationGrantRepository($connection)->replaceWithSuccessor($grant, $terminal, $successor)
        );
    }

    /**
     * Supplies successful, stale, and conflicting renewal results
     *
     * @return iterable<string, array{int, bool}>
     */
    public static function renewalOutcomes(): iterable
    {
        yield 'terminal update succeeds' => [1, false];
        yield 'terminal update loses its match' => [0, false];
        yield 'successor collision' => [1, true];
    }

    /**
     * Reports pending and claimed work using their different eligibility timestamps
     */
    public function testDueWorkSeparatesScheduledTimeFromClaimLease(): void
    {
        $grant = $this->grant();
        $claimed = $this->grant();
        $at = $this->now()->modify('+5 minutes');
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->method('createQueryBuilder')->willReturnCallback(
            static fn (): QueryBuilder => new QueryBuilder($connection)
        );
        $result = $this->createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ActivationGrantRecords::fields($grant),
            array_replace(ActivationGrantRecords::fields($claimed), [
                'delivery_status'      => 'claimed',
                'delivery_lease_until' => '2026-10-21 12:05:00.123456+00:00',
                'revision'             => '4'
            ])
        ]);
        $connection->expects(self::once())->method('executeQuery')->with(
            self::stringContains('g.delivery_lease_until <= :at'),
            [
                'at'      => '2026-10-21 12:05:00.123456+00:00',
                'pending' => 'pending',
                'retry'   => 'retry_pending',
                'claimed' => 'claimed'
            ]
        )->willReturn($result);
        $repository = new PostgresActivationGrantRepository($connection);

        self::assertSame([], $repository->findDue($at, 0));
        $work = $repository->findDue($at, 2);
        self::assertCount(2, $work);
        self::assertTrue($grant->getDelivery()->getId()->equals($work[0]->getDeliveryId()));
        self::assertEquals($this->now(), $work[0]->getDueAt());
        self::assertTrue($claimed->getDelivery()->getId()->equals($work[1]->getDeliveryId()));
        self::assertSame(4, $work[1]->getRevision());
        self::assertEquals($at, $work[1]->getDueAt());
        self::assertSame(CredentialDeliveryStatus::CLAIMED, $work[1]->getStatus());
    }

    /**
     * Returns the activation delivery to erase at the expiry boundary without returning secret material
     */
    public function testExpiryProjectsActivationDeliveryIdentity(): void
    {
        $grant = $this->grant();
        $expires = '2026-10-21 13:00:00.123456+00:00';
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')->with(
            self::stringContains('FROM activation_grants'),
            [$expires, 2]
        )->willReturn([ActivationGrantRecords::fields($grant)]);

        $references = new PostgresActivationGrantRepository($connection)->findExpired($grant->getExpiresAt(), 2);

        self::assertCount(1, $references);
        self::assertSame('activation', $references[0]->getPurpose());
        self::assertSame($grant->getDelivery()->getId()->toString(), $references[0]->getDeliveryId()->toString());
        self::assertNull($references[0]->getEmailChangeGrantId());
        self::assertEquals($grant->getExpiresAt(), $references[0]->getExpiresAt());
    }

    /**
     * Resolves an activation grant by its own identity, by delivery or by latest owner generation
     */
    #[DataProvider('lookupCases')]
    public function testLookupMapsTheStoredGrant(string $mode, bool $found): void
    {
        $grant = $this->grant();
        $connection = $this->createMock(Connection::class);
        $row = $found ? ActivationGrantRecords::fields($grant) : false;
        if ($mode === 'owner') {
            $connection->expects(self::once())->method('fetchAssociative')->with(
                self::stringContains('ORDER BY generation DESC'),
                [$grant->getUserId()->toString()]
            )->willReturn($row);
        } else {
            $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
            $connection->method('createQueryBuilder')->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection)
            );
            $result = $this->createStub(Result::class);
            $result->method('fetchAssociative')->willReturn($row);
            $byDelivery = $mode === 'delivery';
            $connection->expects(self::once())->method('executeQuery')->with(
                self::stringContains($byDelivery ? 'WHERE delivery_id = :id' : 'WHERE id = :id'),
                ['id' => ($byDelivery ? $grant->getDelivery()->getId() : $grant->getId())->toString()]
            )->willReturn($result);
        }
        $repository = new PostgresActivationGrantRepository($connection);

        $loaded = match ($mode) {
            'owner' => $repository->getLatestByUserId($grant->getUserId()),
            'delivery' => $repository->getByDeliveryId($grant->getDelivery()->getId()),
            'grant' => $repository->getById($grant->getId()),
            default => throw new LogicException('Unknown activation lookup fixture.')
        };

        self::assertSame($found ? $grant->getId()->toString() : null, $loaded?->getId()->toString());
    }

    /**
     * Supplies stored and absent activation identity results
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function lookupCases(): iterable
    {
        foreach (['owner', 'delivery', 'grant'] as $mode) {
            yield $mode.' match' => [$mode, true];
            yield $mode.' missing' => [$mode, false];
        }
    }

    /**
     * Appends a successor only behind a stored terminal grant, without rewriting that grant
     */
    #[DataProvider('successorStates')]
    public function testAddSuccessorRequiresATerminalPredecessor(string $state): void
    {
        $grant = $this->grant();
        $successor = $this->grant($grant->getUserId());
        $terminal = $grant->revoke($this->now()->modify('+1 minute'));
        $stored = match ($state) {
            'missing' => false,
            'issued' => ActivationGrantRecords::fields($grant),
            default => ActivationGrantRecords::fields($terminal) + ['generation' => '7']
        };
        $connection = $this->connection($grant->getUserId());
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('FOR UPDATE'),
            [$grant->getUserId()->toString()]
        )->willReturn($stored);
        $connection->expects(self::never())->method('update');
        $accepted = $state === 'terminal';
        $insert = $connection->expects(self::exactly((int) $accepted))->method('insert');
        if ($accepted) {
            $insert->with('activation_grants', self::callback(static function (array $row) use (
                $grant,
                $successor
            ): bool {
                self::assertSame(8, $row['generation']);
                self::assertSame($grant->getId()->toString(), $row['predecessor_id']);
                self::assertSame($successor->getId()->toString(), $row['id']);

                return true;
            }))->willReturn(1);
        }

        self::assertSame($accepted, new PostgresActivationGrantRepository($connection)->addSuccessor($successor));
    }

    /**
     * Supplies latest activation-generation states
     *
     * @return iterable<string, array{string}>
     */
    public static function successorStates(): iterable
    {
        foreach (['missing', 'issued', 'terminal'] as $state) {
            yield $state => [$state];
        }
    }

    /**
     * Rejects a stale snapshot and otherwise records the delivery claim without rewriting the digest
     */
    #[DataProvider('storedSnapshots')]
    public function testReplaceComparesTheStoredRevision(bool $matches): void
    {
        $grant = $this->grant();
        $token = CredentialDeliveryClaimToken::generate();
        $claimed = $grant->claimDelivery($token, $this->now(), $this->now()->modify('+5 minutes'));
        $stored = ActivationGrantRecords::fields($matches ? $grant : $claimed);
        $connection = $this->connection($grant->getUserId());
        $connection->expects(self::once())->method('fetchAssociative')->willReturn($stored);
        $write = $connection->expects(self::exactly((int) $matches))->method('update');
        if ($matches) {
            $write->with(
                'activation_grants',
                self::callback(static function (array $fields) use ($token): bool {
                    self::assertSame('claimed', $fields['delivery_status']);
                    self::assertSame($token->toString(), $fields['delivery_claim_token']);
                    self::assertArrayNotHasKey('credential_digest', $fields);

                    return true;
                }),
                ['id' => $grant->getId()->toString(), 'revision' => 0]
            )->willReturn(1);
        }

        self::assertSame($matches, new PostgresActivationGrantRepository($connection)->replace($grant, $claimed));
    }

    /**
     * Replays package policy before writing mutable delivery state against the observed revision
     */
    #[DataProvider('deliveryOutcomes')]
    public function testReplaceRecordsPackageDeliveryOutcome(string $outcome, string $status, bool $retained): void
    {
        $issued = $this->grant();
        $token = CredentialDeliveryClaimToken::generate();
        $at = $this->now();
        $claimed = $issued->claimDelivery($token, $at, $at->modify('+5 minutes'));
        $failure = CredentialDeliveryFailure::UNEXPECTED_PROVIDER;
        $retry = $claimed->failDelivery($token, $at, $failure);
        $lateAt = $at->modify('+59 minutes');
        $late = $retry->claimDelivery($token, $lateAt, $issued->getExpiresAt());
        [$before, $after] = match ($outcome) {
            'retryable failure' => [$claimed, $retry],
            'request retry' => [$retry, $retry->requestDeliveryRetry()],
            'delivered' => [$claimed, $claimed->confirmDelivery($token, $at)],
            'permanent failure' => [$claimed, $claimed->failDeliveryPermanently($token, $at)],
            'expired before dispatch' => [$issued, $issued->expireDeliveryAt($issued->getExpiresAt())],
            'backoff crosses deadline' => [$late, $late->failDelivery($token, $lateAt, $failure)],
            'consumed' => [$issued, $issued->consume($at)],
            default => throw new LogicException('Unknown delivery outcome fixture.')
        };
        $connection = $this->connection($issued->getUserId());
        $connection->expects(self::once())->method('fetchAssociative')
            ->willReturn(ActivationGrantRecords::fields($before));
        $connection->expects(self::once())->method('update')->with(
            'activation_grants',
            self::callback(static function (array $fields) use ($before, $status, $retained): bool {
                self::assertSame($status, $fields['delivery_status']);
                self::assertSame($before->getRevision() + 1, $fields['revision']);
                self::assertSame($retained ? 'synthetic-encrypted-material' : null, $fields['delivery_ciphertext']);
                self::assertArrayNotHasKey('credential_digest', $fields);

                return true;
            }),
            ['id' => $before->getId()->toString(), 'revision' => $before->getRevision()]
        )->willReturn(1);

        self::assertTrue(new PostgresActivationGrantRepository($connection)->replace($before, $after));
    }

    /**
     * Supplies delivery transitions and whether dispatchable material remains
     *
     * @return iterable<string, array{string, string, bool}>
     */
    public static function deliveryOutcomes(): iterable
    {
        yield 'retryable failure' => ['retryable failure', 'retry_pending', true];
        yield 'request retry' => ['request retry', 'pending', true];
        yield 'delivered' => ['delivered', 'delivered', false];
        yield 'permanent failure' => ['permanent failure', 'permanent_failure', false];
        yield 'expired before dispatch' => ['expired before dispatch', 'expired', false];
        yield 'backoff crosses deadline' => ['backoff crosses deadline', 'expired', false];
        yield 'consumed' => ['consumed', 'invalidated', false];
    }

    /**
     * Rejects only owned check conflicts and propagates unknown persistence failures
     */
    #[DataProvider('writeConflicts')]
    public function testWriteClassifiesOnlyKnownConflicts(string $sqlState, string $constraint, string $result): void
    {
        $grant = $this->grant();
        $connection = $this->connection($grant->getUserId());
        $connection->expects(self::once())->method('fetchAssociative')->willReturn(false);
        $driverError = new PgSqlException('Synthetic violation of constraint "'.$constraint.'"', $sqlState);
        if ($sqlState === '23503') {
            $failure = new ForeignKeyConstraintViolationException($driverError, null);
        } else {
            $failure = new DriverException($driverError, null);
        }
        $connection->expects(self::once())->method('insert')->willThrowException($failure);
        $connection->expects(self::once())->method('rollbackSavepoint');
        if ($result === 'owner lost') {
            $this->expectException(PersistenceConflict::class);
            $this->expectExceptionMessage('The activation grant owner is unavailable.');
        } elseif ($result === 'propagated') {
            $this->expectExceptionObject($failure);
        }

        self::assertFalse(new PostgresActivationGrantRepository($connection)->add($grant));
    }

    /**
     * Supplies owner, table-specific and unrelated database conflicts
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function writeConflicts(): iterable
    {
        yield 'owner reference' => ['23503', 'fk_activation_grants_owner', 'owner lost'];
        yield 'known check' => ['23514', 'ck_activation_grants_terminal', 'rejected'];
        yield 'unrelated check' => ['23514', 'ck_unrelated', 'propagated'];
    }

    /**
     * Isolates the connection while keeping the actual owner and grant fence checks
     */
    private function connection(UserId $owner): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('fetchOne')->with(
            self::stringContains('FOR KEY SHARE'),
            [$owner->toString()]
        )->willReturn(1);
        $connection->expects(self::once())->method('executeQuery')->with(
            self::stringContains('pg_advisory_xact_lock'),
            [$owner->toString(), 24123]
        )->willReturn($this->createStub(Result::class));

        return $connection;
    }

    /**
     * Issues synthetic material for an invitation
     */
    private function grant(?UserId $owner = null): ActivationGrant
    {
        return ActivationGrant::issue(
            ActivationOrigin::PRIVATE_INVITATION,
            $owner ?? UserId::generate(),
            ActivationCredential::fromString(str_repeat('ab', 32)),
            $this->now(),
            $this->now()->modify('+1 hour'),
            EmailAddress::fromString('invite@example.test'),
            'synthetic-encrypted-material'
        );
    }

    /**
     * Supplies the fixed issuance time
     */
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-21T12:00:00.123456Z');
    }
}
