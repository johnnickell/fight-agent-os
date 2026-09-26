<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Hydration\PersistedPasswordResetGrant;
use App\Adapter\Persistence\Repository\PostgresPasswordResetGrantRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDelivery;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PasswordResetGrantRepositoryTest extends TestCase
{
    private Connection $connection;
    private PostgresPasswordResetGrantRepository $grants;
    private DoctrineTransactionalUnitOfWork $unitOfWork;
    private UserId $userId;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('TRUNCATE password_reset_grants, refresh_session_used_credentials, '
            . 'refresh_sessions, user_role_assignments, user_email_claims, users, role_permissions, roles, permissions CASCADE');
        $this->userId = UserId::generate();
        $this->now = new DateTimeImmutable('2026-10-01T12:00:00+00:00');
        $this->connection->insert('users', [
            'id' => $this->userId->toString(), 'email' => 'reset@example.test', 'state' => 'pending_activation',
            'password_hash' => null, 'authentication_version' => 1, 'authentication_authority_revision' => 0,
            'authorization_assignment_revision' => 0, 'pending_email_change' => null,
            'email_change_reservation_revision' => 0, 'canonical_email_revision' => 0,
            'created_at' => $this->now->format('Y-m-d H:i:s.uP'), 'updated_at' => $this->now->format('Y-m-d H:i:s.uP'),
        ]);
        $this->grants = new PostgresPasswordResetGrantRepository($this->connection);
        $config = ORMSetup::createAttributeMetadataConfiguration([], true);
        $config->enableNativeLazyObjects(true);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork(new EntityManager($this->connection, $config));
    }

    public function test_round_trip_delivery_lifecycle_due_order_and_secret_absence(): void
    {
        [$grant, $raw] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($grant)));
        self::assertFalse($this->commit(fn(): bool => $this->grants->add($grant)));
        self::assertNull($this->grants->getById(PasswordResetGrantId::generate()));
        self::assertNull($this->grants->getByDeliveryId(PasswordResetDeliveryId::generate()));
        self::assertNull($this->grants->getLatestByUserId(UserId::generate()));
        $stored = $this->grants->getById($grant->getId());
        self::assertNotNull($stored);
        self::assertSame($grant->getCredentialHash(), $stored->getCredentialHash());
        self::assertTrue($stored->matchesCredential($raw));
        self::assertSame($grant->getId()->toString(), $this->grants->getByDeliveryId($grant->getDelivery()->getId())?->getId()->toString());
        self::assertSame('password_reset', $stored->purpose());
        self::assertSame(0, $stored->getRevision());
        self::assertSame('encrypted-material', $stored->getDelivery()->getEncryptedMaterial()?->reveal());
        self::assertSame([], $this->grants->findDue($this->now->modify('-1 second'), 10));
        self::assertSame([], $this->grants->findDue($this->now, 0));
        self::assertCount(1, $this->grants->findDue($this->now, 10));
        self::assertSame($grant->getDelivery()->getId()->toString(), $this->grants->findDue($this->now, 10)[0]->getDeliveryId()->toString());

        $claim = CredentialDeliveryClaimToken::generate();
        $claimed = $stored->claimDelivery($claim, $this->now, $this->now->modify('+5 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->grants->replace($stored, $claimed)));
        $rehydrated = $this->grants->getById($grant->getId());
        self::assertNotNull($rehydrated);
        self::assertSame(CredentialDeliveryStatus::CLAIMED, $rehydrated->getDelivery()->getStatus());
        self::assertSame($claim->toString(), $rehydrated->getDelivery()->getClaimToken()?->toString());
        self::assertSame([], $this->grants->findDue($this->now, 10));
        self::assertSame(1, $this->grants->findDue($this->now->modify('+5 minutes'), 10)[0]->getRevision());

        $failed = $rehydrated->failDelivery($claim, $this->now->modify('+1 minute'), CredentialDeliveryFailure::UNEXPECTED_PROVIDER);
        self::assertTrue($this->commit(fn(): bool => $this->grants->replace($rehydrated, $failed)));
        $retry = $this->grants->getById($grant->getId());
        self::assertNotNull($retry);
        self::assertSame(CredentialDeliveryStatus::RETRY_PENDING, $retry->getDelivery()->getStatus());
        self::assertSame(1, $retry->getDelivery()->getAttemptCount());
        self::assertSame(CredentialDeliveryFailure::UNEXPECTED_PROVIDER, $retry->getDelivery()->getLastFailure());
        self::assertSame([], $this->grants->findDue($this->now, 10));
        self::assertCount(1, $this->grants->findDue($retry->getDelivery()->getDueAt(), 10));
        self::assertTrue($this->commit(fn(): bool => $this->grants->replace($retry, $retry->requestDeliveryRetry())));
        $current = $this->grants->getLatestByUserId($this->userId);
        self::assertNotNull($current);
        self::assertSame(CredentialDeliveryStatus::PENDING, $current->getDelivery()->getStatus());
        self::assertSame(3, $current->getRevision());
        $nextToken = CredentialDeliveryClaimToken::generate();
        $nextClaim = $current->claimDelivery($nextToken, $current->getDelivery()->getDueAt(),
            $current->getDelivery()->getDueAt()->modify('+5 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->grants->replace($current, $nextClaim)));
        $delivered = $nextClaim->confirmDelivery($nextToken, $nextClaim->getDelivery()->getClaimedAt());
        self::assertTrue($this->commit(fn(): bool => $this->grants->replace($nextClaim, $delivered)));
        self::assertNull($this->grants->getById($grant->getId())?->getDelivery()->getEncryptedMaterial());
        self::assertSame(CredentialDeliveryStatus::DELIVERED,
            $this->grants->getById($grant->getId())?->getDelivery()->getStatus());
        self::assertSame([], $this->grants->findDue($this->now->modify('+10 minutes'), 10));
        $row = $this->connection->fetchAssociative('SELECT * FROM password_reset_grants WHERE id = ?', [$grant->getId()->toString()]);
        self::assertIsArray($row);
        self::assertNotContains($raw->toString(), array_values($row));
    }

    public function test_due_discovery_is_bounded_ordered_and_excludes_old_generations(): void
    {
        $otherUser = UserId::generate();
        $this->connection->executeStatement(<<<'SQL'
INSERT INTO users (id, email, state, password_hash, authentication_version,
    authentication_authority_revision, authorization_assignment_revision, pending_email_change,
    email_change_reservation_revision, canonical_email_revision, created_at, updated_at)
SELECT ?, 'other-reset@example.test', state, password_hash, authentication_version,
    authentication_authority_revision, authorization_assignment_revision, pending_email_change,
    email_change_reservation_revision, canonical_email_revision, created_at, updated_at
FROM users WHERE id = ?
SQL, [$otherUser->toString(), $this->userId->toString()]);
        [$first] = $this->grant();
        [$second] = $this->grant(userId: $otherUser);
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($first)));
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($second)));
        $expected = [$first->getDelivery()->getId()->toString(), $second->getDelivery()->getId()->toString()];
        sort($expected);
        $due = $this->grants->findDue($this->now, 1);
        self::assertCount(1, $due);
        self::assertSame($expected[0], $due[0]->getDeliveryId()->toString());
        self::assertSame($expected, array_map(static fn($item): string => $item->getDeliveryId()->toString(),
            $this->grants->findDue($this->now, 10)));
        $terminal = $first->revoke($this->now->modify('+1 minute'));
        self::assertTrue($this->commit(fn(): bool => $this->grants->replace($first, $terminal)));
        [$successor] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->appendAfterTerminal($terminal, $successor)));
        $dueIds = array_map(static fn($item): string => $item->getDeliveryId()->toString(),
            $this->grants->findDue($this->now, 10));
        self::assertNotContains($first->getDelivery()->getId()->toString(), $dueIds);
        self::assertContains($successor->getDelivery()->getId()->toString(), $dueIds);
        self::assertCount(2, $dueIds);
    }

    public function test_terminal_append_and_successor_fence_historical_digests(): void
    {
        [$first, $raw] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($first)));
        [$candidate] = $this->grant();
        self::assertFalse($this->commit(fn(): bool => $this->grants->appendAfterTerminal($first, $candidate)));
        $terminal = $first->consume($this->now->modify('+1 minute'));
        self::assertTrue($this->commit(fn(): bool => $this->grants->replace($first, $terminal)));
        self::assertFalse($this->commit(fn(): bool => $this->grants->replace($first, $first->revoke($this->now))));
        self::assertTrue($this->commit(fn(): bool => $this->grants->appendAfterTerminal($terminal, $candidate)));
        self::assertFalse($this->commit(fn(): bool => $this->grants->appendAfterTerminal($terminal, $this->grant()[0])));
        self::assertSame($candidate->getId()->toString(), $this->grants->getLatestByUserId($this->userId)?->getId()->toString());
        self::assertEquals($terminal->getConsumedAt(), $this->grants->getById($first->getId())?->getConsumedAt());
        [$reused] = $this->grant($raw);
        $terminalCandidate = $candidate->revoke($this->now->modify('+2 minutes'));
        self::assertFalse($this->commit(fn(): bool => $this->grants->replaceWithSuccessor($candidate, $terminalCandidate, $reused)));
        self::assertTrue($this->grants->getLatestByUserId($this->userId)?->isIssued());
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM password_reset_grants'));
    }

    public function test_succession_rejects_stale_completion_and_rolls_back_after_second_write_failure(): void
    {
        [$first] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($first)));
        [$successor] = $this->grant();
        $terminal = $first->revoke($this->now->modify('+1 minute'));
        try {
            $this->commit(function () use ($first, $terminal, $successor): void {
                self::assertTrue($this->grants->replaceWithSuccessor($first, $terminal, $successor));
                throw new RuntimeException('Injected caller failure');
            });
            self::fail('The caller failure must roll back both writes.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected caller failure', $exception->getMessage());
        }
        self::assertSame(0, $this->grants->getById($first->getId())?->getRevision());
        self::assertNull($this->grants->getById($successor->getId()));
        $config = ORMSetup::createAttributeMetadataConfiguration([], true);
        $config->enableNativeLazyObjects(true);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork(new EntityManager($this->connection, $config));

        // An FK failure after the predecessor update rolls back the repository savepoint.
        $missingUser = UserId::generate();
        [$other] = $this->grant(userId: $missingUser);
        self::assertFalse($this->commit(fn(): bool => $this->grants->replaceWithSuccessor($first, $terminal, $other)));
        self::assertSame(0, $this->grants->getById($first->getId())?->getRevision());
        self::assertTrue($this->commit(fn(): bool => $this->grants->replaceWithSuccessor($first, $terminal, $successor)));
        self::assertFalse($this->commit(fn(): bool => $this->grants->replace($first, $first->consume($this->now))));
        self::assertFalse($this->commit(fn(): bool => $this->grants->replaceWithSuccessor($first, $terminal, $this->grant()[0])));
        self::assertEquals($terminal->getRevokedAt(), $this->grants->getById($first->getId())?->getRevokedAt());
    }

    public function test_missing_user_and_cross_purpose_are_isolated(): void
    {
        [$missing] = $this->grant(userId: UserId::generate());
        self::assertFalse($this->commit(fn(): bool => $this->grants->add($missing)));
        self::assertNull($this->grants->getById($missing->getId()));
        [$reset] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($reset)));
        self::assertFalse($this->connection->fetchOne('SELECT id FROM password_reset_grants WHERE id = ?', [PasswordResetGrantId::generate()->toString()]));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM password_reset_grants WHERE user_id != ?', [$this->userId->toString()]));
    }

    public function test_second_write_constraint_failure_rolls_back_terminalization(): void
    {
        [$first] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($first)));
        $terminal = $first->revoke($this->now->modify('+1 minute'));
        [$middle] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->replaceWithSuccessor($first, $terminal, $middle)));
        [$fresh] = $this->grant();
        $duplicateDelivery = PasswordResetDelivery::create($first->getDelivery()->getId(), $this->userId,
            $fresh->getDelivery()->getEmail(), 'encrypted-material', $fresh->getExpiresAt(), $this->now);
        $duplicate = PersistedPasswordResetGrant::reconstitute($fresh->getId(), $this->userId,
            $fresh->getCredentialHash(), $fresh->getExpiresAt(), $duplicateDelivery, null, null, 0);
        $nextTerminal = $middle->revoke($this->now->modify('+2 minutes'));
        self::assertFalse($this->commit(fn(): bool => $this->grants->replaceWithSuccessor($middle, $nextTerminal, $duplicate)));
        self::assertSame(0, $this->grants->getById($middle->getId())?->getRevision());
        self::assertNull($this->grants->getById($duplicate->getId()));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM password_reset_grants'));
        self::assertTrue($this->commit(fn(): bool => $this->grants->replaceWithSuccessor($middle, $nextTerminal, $fresh)));
    }

    public function test_fabricated_predecessor_and_duplicate_completion_do_not_write(): void
    {
        [$first] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($first)));
        $fake = PersistedPasswordResetGrant::reconstitute($first->getId(), $this->userId,
            $first->getCredentialHash(), $first->getExpiresAt(),
            PasswordResetDelivery::create($first->getDelivery()->getId(), $this->userId,
                $first->getDelivery()->getEmail(), 'different-encrypted-material', $first->getExpiresAt(), $this->now),
            null, null, 0);
        self::assertFalse($this->commit(fn(): bool => $this->grants->replace($fake, $fake->consume($this->now))));
        $consumed = $first->consume($this->now->modify('+1 minute'));
        self::assertTrue($this->commit(fn(): bool => $this->grants->replace($first, $consumed)));
        self::assertFalse($this->commit(fn(): bool => $this->grants->replace($first, $consumed)));
        self::assertSame(1, $this->grants->getLatestByUserId($this->userId)?->getRevision());
    }

    public function test_competing_completions_allow_one_terminal_revision(): void
    {
        [$first] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($first)));
        $consumed = $first->consume($this->now->modify('+1 minute'));
        $revoked = $first->revoke($this->now->modify('+2 minutes'));
        $other = $this->connection();
        $competing = new PostgresPasswordResetGrantRepository($other);
        $this->connection->beginTransaction();
        $other->beginTransaction();
        $other->executeStatement("SET LOCAL lock_timeout = '100ms'");
        try {
            self::assertTrue($this->grants->replace($first, $consumed));
            try {
                $competing->replace($first, $revoked);
                self::fail('The competing completion must wait for the per-user lock.');
            } catch (DriverException) {
                self::assertTrue(true);
            }
            $this->connection->commit();
            $other->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($other->isTransactionActive()) {
                $other->rollBack();
            }
            $other->close();
        }
        self::assertFalse($this->commit(fn(): bool => $this->grants->replace($first, $revoked)));
        self::assertTrue($this->grants->getById($first->getId())?->isConsumed());
        self::assertFalse($this->grants->getById($first->getId())?->isRevoked());
    }

    public function test_competing_terminal_append_waits_and_rejects_stale_predecessor(): void
    {
        [$first] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($first)));
        $terminal = $first->consume($this->now->modify('+1 minute'));
        self::assertTrue($this->commit(fn(): bool => $this->grants->replace($first, $terminal)));
        [$winner] = $this->grant();
        [$loser] = $this->grant();
        $other = $this->connection();
        $competing = new PostgresPasswordResetGrantRepository($other);
        $this->connection->beginTransaction();
        $other->beginTransaction();
        $other->executeStatement("SET LOCAL lock_timeout = '100ms'");
        try {
            self::assertTrue($this->grants->appendAfterTerminal($terminal, $winner));
            try {
                $competing->appendAfterTerminal($terminal, $loser);
                self::fail('The competing append must wait for the per-user lock.');
            } catch (DriverException) {
                self::assertTrue(true);
            }
            $this->connection->commit();
            $other->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($other->isTransactionActive()) {
                $other->rollBack();
            }
            $other->close();
        }
        self::assertFalse($this->commit(fn(): bool => $this->grants->appendAfterTerminal($terminal, $loser)));
        self::assertSame($winner->getId()->toString(), $this->grants->getLatestByUserId($this->userId)?->getId()->toString());
    }

    public function test_competing_terminal_operations_wait_and_only_one_successor_wins(): void
    {
        [$first] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->grants->add($first)));
        [$winner] = $this->grant();
        [$loser] = $this->grant();
        $terminal = $first->revoke($this->now->modify('+1 minute'));
        $other = $this->connection();
        $competing = new PostgresPasswordResetGrantRepository($other);
        $this->connection->beginTransaction();
        $other->beginTransaction();
        $other->executeStatement("SET LOCAL lock_timeout = '100ms'");
        try {
            self::assertTrue($this->grants->replaceWithSuccessor($first, $terminal, $winner));
            try {
                $competing->replaceWithSuccessor($first, $terminal, $loser);
                self::fail('The competing reset write must wait for the per-user lock.');
            } catch (DriverException) {
                self::assertTrue(true);
            }
            $this->connection->commit();
            $other->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($other->isTransactionActive()) {
                $other->rollBack();
            }
            $other->close();
        }
        self::assertFalse($this->commit(fn(): bool => $this->grants->replaceWithSuccessor($first, $terminal, $loser)));
        self::assertSame($winner->getId()->toString(), $this->grants->getLatestByUserId($this->userId)?->getId()->toString());
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM password_reset_grants'));
    }

    /**
     * @return array{PasswordResetGrant, PasswordResetCredential}
     */
    private function grant(?PasswordResetCredential $credential = null, ?UserId $userId = null): array
    {
        $credential ??= PasswordResetCredential::fromString(bin2hex(random_bytes(32)));

        return [PasswordResetGrant::issue($userId ?? $this->userId, $credential, $this->now,
            $this->now->modify('+1 hour'), EmailAddress::fromString('reset@example.test'), 'encrypted-material'), $credential];
    }

    private function commit(callable $work): mixed
    {
        return $this->unitOfWork->commitTransactional($work);
    }

    private function connection(): Connection
    {
        $url = getenv('TEST_DATABASE_URL');
        $host = getenv('TEST_DATABASE_ALLOWED_HOST');
        self::assertIsString($url);
        self::assertIsString($host);
        $guard = new DatabaseTargetGuard(array_values(array_filter(array_map('trim', explode(',', $host)))));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $connection = DriverManager::getConnection((new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql',
        ]))->parse($url));
        $guard->assertConnected($connection, $expected);

        return $connection;
    }
}
