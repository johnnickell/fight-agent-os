<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\ActivationGrant\ActivationGrantRecords;
use App\Adapter\Persistence\AccessControl\ActivationGrant\PostgresActivationGrantRepository;
use App\Adapter\Persistence\PersistenceConflict;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PgSqlException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin;
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
