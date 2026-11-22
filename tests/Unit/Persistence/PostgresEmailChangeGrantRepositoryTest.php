<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\EmailChangeGrant\EmailChangeGrantRecords;
use App\Adapter\Persistence\AccessControl\EmailChangeGrant\PostgresEmailChangeGrantRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PgSqlException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeCredential;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrant;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeRequestOrigin;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects email-change generation and delivery state across the persistence boundary
 */
final class PostgresEmailChangeGrantRepositoryTest extends TestCase
{
    /**
     * Refuses issuance outside a transaction before taking the owner lock
     */
    public function testIssuanceRequiresATransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::never())->method('executeQuery');
        $connection->expects(self::never())->method('insert');

        $this->expectException(LogicException::class);
        new PostgresEmailChangeGrantRepository($connection)->add($this->grant());
    }

    /**
     * Rejects an already changed grant before locking its owner
     */
    public function testInitialGrantMustBePristine(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::never())->method('executeQuery');
        $connection->expects(self::never())->method('insert');

        $revoked = $this->grant()->revoke($this->now());
        self::assertFalse(new PostgresEmailChangeGrantRepository($connection)->add($revoked));
    }

    /**
     * Writes generation zero only once and rolls back on a conflicting insert
     */
    #[DataProvider('issuanceOutcomes')]
    public function testInitialIssuanceIsGuarded(bool $exists, bool $collision): void
    {
        $grant = $this->grant();
        $connection = $this->writeConnection($grant->getUserId());
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('ORDER BY generation DESC'),
            [$grant->getUserId()->toString()]
        )->willReturn($exists ? EmailChangeGrantRecords::fields($grant) : false);
        $insert = $connection->expects(self::exactly((int) !$exists))->method('insert');
        if (!$exists) {
            $insert->with('email_change_grants', self::callback(static function (array $row) use ($grant): bool {
                self::assertSame(0, $row['generation']);
                self::assertSame($grant->getId()->toString(), $row['id']);
                self::assertSame('pending', $row['delivery_status']);
                self::assertNotContains(str_repeat('ab', 32), $row);

                return true;
            }));
            if ($collision) {
                $insert->willThrowException(new UniqueConstraintViolationException(
                    new PgSqlException('Synthetic uniqueness conflict', '23505'),
                    null
                ));
            } else {
                $insert->willReturn(1);
            }
        }
        $connection->expects(self::exactly((int) $collision))->method('rollbackSavepoint');

        self::assertSame(!$exists && !$collision, new PostgresEmailChangeGrantRepository($connection)->add($grant));
    }

    /**
     * Supplies initial generation outcomes
     *
     * @return iterable<string, array{bool, bool}>
     */
    public static function issuanceOutcomes(): iterable
    {
        yield 'new owner generation' => [false, false];
        yield 'existing generation' => [true, false];
        yield 'conflicting credential' => [false, true];
    }

    /**
     * Appends only after the exact authoritative terminal generation and for the same owner
     */
    #[DataProvider('successorOutcomes')]
    public function testAppendFencesThePredecessor(string $scenario): void
    {
        $issued = $this->grant();
        $terminal = $issued->revoke($this->now());
        $before = $scenario === 'live' ? $issued : $terminal;
        $successor = $this->grant($scenario === 'different owner' ? null : $issued->getUserId());
        $stored = EmailChangeGrantRecords::fields($before) + ['generation' => '5'];
        if ($scenario === 'stale') {
            $stored['revision'] = 9;
        }
        $connection = $this->writeConnection($issued->getUserId());
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains('FOR UPDATE'),
            [$issued->getUserId()->toString()]
        )->willReturn($stored);
        $valid = $scenario === 'terminal';
        $insert = $connection->expects(self::exactly((int) $valid))->method('insert');
        if ($valid) {
            $insert->with('email_change_grants', self::callback(static function (array $row) use ($successor): bool {
                self::assertSame(6, $row['generation']);
                self::assertSame($successor->getId()->toString(), $row['id']);

                return true;
            }))->willReturn(1);
        }

        self::assertSame($valid, new PostgresEmailChangeGrantRepository($connection)->appendAfterTerminal(
            $before,
            $successor
        ));
    }

    /**
     * Supplies successor admission scenarios
     *
     * @return iterable<string, array{string}>
     */
    public static function successorOutcomes(): iterable
    {
        foreach (['terminal', 'live', 'stale', 'different owner'] as $scenario) {
            yield $scenario => [$scenario];
        }
    }

    /**
     * Persists only the mutable fields and fences the update against the exact stored revision
     */
    #[DataProvider('transitions')]
    public function testReplacePersistsGuardedTransition(string $transition, string $status, int $updated): void
    {
        $issued = $this->grant();
        $token = CredentialDeliveryClaimToken::generate();
        $at = $this->now();
        $claimed = $issued->claimDelivery($token, $at, $at->modify('+5 minutes'));
        $failure = CredentialDeliveryFailure::UNEXPECTED_PROVIDER;
        $retry = $claimed->failDelivery($token, $at, $failure);
        $late = $issued->claimDelivery($token, $at->modify('+59 minutes'), $issued->getExpiresAt());
        [$before, $after] = match ($transition) {
            'claim' => [$issued, $claimed],
            'retryable failure' => [$claimed, $retry],
            'retry request' => [$retry, $retry->requestDeliveryRetry()],
            'confirmed' => [$claimed, $claimed->confirmDelivery($token, $at)],
            'permanent failure' => [$claimed, $claimed->failDeliveryPermanently($token, $at)],
            'delivery expiry' => [$issued, $issued->expireDeliveryAt($issued->getExpiresAt())],
            'backoff past expiry' => [$late, $late->failDelivery($token, $at->modify('+59 minutes'), $failure)],
            'consumed' => [$issued, $issued->consume($at)],
            'revoked' => [$issued, $issued->revoke($at)],
            'grant expiry' => [$issued, $issued->expireAt($issued->getExpiresAt())],
            default => throw new LogicException('Unknown transition fixture.')
        };
        $connection = $this->writeConnection($issued->getUserId());
        $connection->expects(self::once())->method('fetchAssociative')
            ->willReturn(EmailChangeGrantRecords::fields($before));
        $connection->expects(self::once())->method('update')->with(
            'email_change_grants',
            self::callback(static function (array $fields) use ($before, $status): bool {
                self::assertSame($status, $fields['delivery_status']);
                self::assertSame($before->getRevision() + 1, $fields['revision']);
                self::assertArrayNotHasKey('credential_digest', $fields);
                self::assertArrayNotHasKey('requester_id', $fields);
                self::assertArrayNotHasKey('old_mailbox', $fields);
                self::assertSame(
                    in_array($status, ['pending', 'retry_pending', 'claimed'], true) ? 'fixture-ciphertext' : null,
                    $fields['delivery_ciphertext']
                );

                return true;
            }),
            ['id' => $before->getId()->toString(), 'revision' => $before->getRevision()]
        )->willReturn($updated);

        self::assertSame($updated === 1, new PostgresEmailChangeGrantRepository($connection)->replace($before, $after));
    }

    /**
     * Supplies delivery and grant transitions with their stored status
     *
     * @return iterable<string, array{string, string, int}>
     */
    public static function transitions(): iterable
    {
        yield 'claim' => ['claim', 'claimed', 1];
        yield 'lost update' => ['claim', 'claimed', 0];
        yield 'retryable failure' => ['retryable failure', 'retry_pending', 1];
        yield 'retry request' => ['retry request', 'pending', 1];
        yield 'confirmed' => ['confirmed', 'delivered', 1];
        yield 'permanent failure' => ['permanent failure', 'permanent_failure', 1];
        yield 'delivery expiry' => ['delivery expiry', 'expired', 1];
        yield 'backoff past expiry' => ['backoff past expiry', 'expired', 1];
        yield 'consumed' => ['consumed', 'invalidated', 1];
        yield 'revoked' => ['revoked', 'invalidated', 1];
        yield 'grant expiry' => ['grant expiry', 'invalidated', 1];
    }

    /**
     * Does not write a forged delivery change, a reused terminal grant or an obsolete snapshot
     */
    #[DataProvider('rejectedTransitions')]
    public function testReplaceRejectsInvalidTransition(string $scenario): void
    {
        $grant = $this->grant();
        $claimed = $grant->claimDelivery(
            CredentialDeliveryClaimToken::generate(),
            $this->now(),
            $this->now()->modify('+5 minutes')
        );
        $terminal = $grant->revoke($this->now());
        $before = $scenario === 'terminal' ? $terminal : $grant;
        $stored = EmailChangeGrantRecords::fields($scenario === 'stale' ? $claimed : $before);
        $after = match ($scenario) {
            'unrelated' => $this->grant($grant->getUserId()),
            'stale' => $claimed,
            'terminal' => EmailChangeGrantRecords::hydrate(array_replace($stored, ['revision' => 2])),
            'bare invalidation' => EmailChangeGrantRecords::hydrate(array_replace(
                EmailChangeGrantRecords::fields($terminal),
                ['revoked_at' => null]
            )),
            'forged expiry' => EmailChangeGrantRecords::hydrate(array_replace(
                EmailChangeGrantRecords::fields($grant->expireDeliveryAt($grant->getExpiresAt())),
                ['delivery_last_outcome_at' => '2026-10-21 12:03:00.000000+00:00']
            )),
            default => throw new LogicException('Unknown rejection fixture.')
        };
        $connection = $this->writeConnection($grant->getUserId());
        $connection->expects(self::once())->method('fetchAssociative')->willReturn($stored);
        $connection->expects(self::never())->method('update');

        self::assertFalse(new PostgresEmailChangeGrantRepository($connection)->replace($before, $after));
    }

    /**
     * Supplies obsolete and incompatible transitions that must not write
     *
     * @return iterable<string, array{string}>
     */
    public static function rejectedTransitions(): iterable
    {
        foreach (['unrelated', 'stale', 'terminal', 'bare invalidation', 'forged expiry'] as $scenario) {
            yield $scenario => [$scenario];
        }
    }

    /**
     * Resolves grant and delivery identities independently without inventing an absent record
     */
    #[DataProvider('lookupModes')]
    public function testLookupReturnsTheStoredIdentity(bool $byDelivery, bool $found): void
    {
        $grant = $this->grant();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')->with(
            self::stringContains($byDelivery ? 'delivery_id = ?' : 'user_id = ? ORDER BY generation DESC'),
            [$byDelivery ? $grant->getDelivery()->getId()->toString() : $grant->getUserId()->toString()]
        )->willReturn($found ? EmailChangeGrantRecords::fields($grant) : false);
        $repository = new PostgresEmailChangeGrantRepository($connection);

        if ($byDelivery) {
            $loaded = $repository->getByDeliveryId($grant->getDelivery()->getId());
        } else {
            $loaded = $repository->getLatestByUserId($grant->getUserId());
        }

        self::assertSame($found ? $grant->getId()->toString() : null, $loaded?->getId()->toString());
    }

    /**
     * Supplies identity lookups with and without stored matches
     *
     * @return iterable<string, array{bool, bool}>
     */
    public static function lookupModes(): iterable
    {
        yield 'delivery exists' => [true, true];
        yield 'unknown delivery' => [true, false];
        yield 'latest grant' => [false, true];
        yield 'no grant for owner' => [false, false];
    }

    /**
     * Projects claimed work against its lease deadline, not its initial delivery schedule
     */
    public function testDueWorkUsesTheClaimLease(): void
    {
        $grant = $this->grant();
        $at = $this->now()->modify('+5 minutes');
        $claimed = $grant->claimDelivery(CredentialDeliveryClaimToken::generate(), $this->now(), $at);
        $connection = $this->createMock(Connection::class);
        $timestamp = '2026-10-21 12:05:00.000000+00:00';
        $connection->expects(self::once())->method('fetchAllAssociative')->with(
            self::stringContains('g.delivery_lease_until <= ?'),
            [$timestamp, $timestamp, $timestamp, 1]
        )->willReturn([EmailChangeGrantRecords::fields($claimed)]);
        $repository = new PostgresEmailChangeGrantRepository($connection);

        self::assertSame([], $repository->findDue($at, 0));
        $work = $repository->findDue($at, 1);
        self::assertCount(1, $work);
        self::assertSame('email_change', $work[0]->getPurpose());
        self::assertTrue($grant->getDelivery()->getId()->equals($work[0]->getDeliveryId()));
        self::assertEquals($at, $work[0]->getDueAt());
        self::assertSame(1, $work[0]->getRevision());
        self::assertSame(CredentialDeliveryStatus::CLAIMED, $work[0]->getStatus());
    }

    /**
     * Returns the authority reference for expiry even after delivery material has been discarded
     */
    public function testExpiryIncludesAnAlreadyDeliveredGrant(): void
    {
        $grant = $this->grant();
        $connection = $this->createMock(Connection::class);
        $deadline = '2026-10-21 13:00:00.000000+00:00';
        $connection->expects(self::once())->method('fetchAllAssociative')->with(
            self::callback(static fn (string $sql): bool => str_contains($sql, 'FROM email_change_grants')
                && !str_contains($sql, 'ciphertext')),
            [$deadline, 3]
        )->willReturn([[
            'id'              => $grant->getId()->toString(),
            'delivery_id'     => $grant->getDelivery()->getId()->toString(),
            'user_id'         => $grant->getUserId()->toString(),
            'expires_at'      => $deadline,
            'revision'        => '2',
            'delivery_status' => 'delivered'
        ]]);

        $expired = new PostgresEmailChangeGrantRepository($connection)->findExpired($grant->getExpiresAt(), 3);

        self::assertCount(1, $expired);
        self::assertSame('email_change', $expired[0]->getPurpose());
        self::assertSame($grant->getId()->toString(), $expired[0]->getEmailChangeGrantId()?->toString());
        self::assertEquals($grant->getExpiresAt(), $expired[0]->getExpiresAt());
        self::assertSame(2, $expired[0]->getRevision());
        self::assertSame(CredentialDeliveryStatus::DELIVERED, $expired[0]->getStatus());
    }

    /**
     * Isolates the connection while requiring the write fence for the expected owner
     */
    private function writeConnection(UserId $owner): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->expects(self::once())->method('executeQuery')->with(
            self::stringContains('pg_advisory_xact_lock'),
            [$owner->toString(), 24124]
        )->willReturn($this->createStub(Result::class));

        return $connection;
    }

    /**
     * Issues synthetic delivery material with a fixed one-hour expiry
     */
    private function grant(?UserId $owner = null): EmailChangeGrant
    {
        return EmailChangeGrant::issue(
            $owner ?? UserId::generate(),
            EmailChangeCredential::fromString(str_repeat('ab', 32)),
            $this->now(),
            $this->now()->modify('+1 hour'),
            EmailAddress::fromString('new@example.test'),
            'fixture-ciphertext',
            7,
            new EmailChangeRequestOrigin(UserId::generate(), EmailAddress::fromString('old@example.test'))
        );
    }

    /**
     * Supplies a fixed issuance time
     */
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-21T12:00:00Z');
    }
}
