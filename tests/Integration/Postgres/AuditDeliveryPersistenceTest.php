<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Repository\PostgresActivationGrantRepository;
use App\Adapter\Persistence\Repository\PostgresAuditEvidenceRepository;
use App\Adapter\Persistence\Repository\PostgresEmailChangeGrantRepository;
use App\Adapter\Persistence\Repository\PostgresPasswordResetGrantRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\QueryHandler\FindDueCredentialDeliveriesHandler;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentId;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeCredential;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrant;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\SessionRevocationReason;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuditDeliveryPersistenceTest extends TestCase
{
    private Connection $connection;
    private PostgresEmailChangeGrantRepository $changes;
    private PostgresAuditEvidenceRepository $audit;
    private DoctrineTransactionalUnitOfWork $unit;
    private UserId $userId;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $url = getenv('TEST_DATABASE_URL');
        $host = getenv('TEST_DATABASE_ALLOWED_HOST');
        self::assertIsString($url);
        self::assertIsString($host);
        $guard = new DatabaseTargetGuard(array_values(array_filter(array_map('trim', explode(',', $host)))));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection((new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql',
        ]))->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement('TRUNCATE audit_evidence, email_change_grants, activation_grants, '
            . 'password_reset_grants, refresh_session_used_credentials, refresh_sessions, user_role_assignments, '
            . 'user_email_claims, users, role_permissions, roles, permissions CASCADE');
        $this->now = new DateTimeImmutable('2026-10-01T12:00:00+00:00');
        $this->userId = UserId::generate();
        $this->connection->insert('users', [
            'id' => $this->userId->toString(), 'email' => 'audit@example.test', 'state' => 'pending_activation',
            'password_hash' => null, 'authentication_version' => 1, 'authentication_authority_revision' => 0,
            'authorization_assignment_revision' => 0, 'pending_email_change' => null,
            'email_change_reservation_revision' => 0, 'canonical_email_revision' => 0,
            'created_at' => $this->date($this->now), 'updated_at' => $this->date($this->now),
        ]);
        $this->changes = new PostgresEmailChangeGrantRepository($this->connection);
        $this->audit = new PostgresAuditEvidenceRepository($this->connection);
        $config = ORMSetup::createAttributeMetadataConfiguration([], true);
        $config->enableNativeLazyObjects(true);
        $this->unit = new DoctrineTransactionalUnitOfWork(new EntityManager($this->connection, $config));
    }

    public function test_audit_round_trips_typed_subjects_and_rolls_back_with_originating_work(): void
    {
        $agent = AgentId::generate();
        $this->commit(function () use ($agent): void {
            $this->audit->add(AuditEvidence::record('operator', 'user.invited', $this->userId));
            $this->audit->add(AuditEvidence::agentProvisioned('operator', $agent));
        });
        $rows = $this->connection->fetchAllAssociative('SELECT actor_id, action, subject_type, subject_id, context FROM audit_evidence ORDER BY id');
        self::assertCount(2, $rows);
        self::assertSame(['user', 'agent'], array_column($rows, 'subject_type'));
        self::assertSame([$this->userId->toString(), $agent->toString()], array_column($rows, 'subject_id'));
        self::assertSame(['user.invited', 'agent.provisioned'], array_column($rows, 'action'));
        self::assertSame(['operator', 'operator'], array_column($rows, 'actor_id'));
        self::assertSame(['{}', '{}'], array_column($rows, 'context'));
        [$grant] = $this->grant();
        try {
            $this->commit(function () use ($grant): void {
                self::assertTrue($this->changes->add($grant));
                $this->audit->add(AuditEvidence::record('operator', 'user.email_change_administratively_requested', $this->userId));
                throw new RuntimeException('caller rollback');
            });
            self::fail('Expected caller rollback.');
        } catch (RuntimeException $exception) {
            self::assertSame('caller rollback', $exception->getMessage());
        }
        self::assertNull($this->changes->getLatestByUserId($this->userId));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    public function test_invalid_and_secret_bearing_context_does_not_write(): void
    {
        $invalid = new class($this->userId) extends AuditEvidence {
            public function __construct(UserId $user)
            {
                parent::__construct('operator', 'user.invited', $user, ['access_token' => 'private']);
            }
        };
        try {
            $this->commit(fn() => $this->audit->add($invalid));
            self::fail('Unsupported context must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Audit evidence contains unsupported public fields.', $exception->getMessage());
        }
        $oversized = new class($this->userId) extends AuditEvidence {
            public function __construct(UserId $user)
            {
                parent::__construct(str_repeat('a', 129), 'user.invited', $user);
            }
        };
        try {
            $this->commit(fn() => $this->audit->add($oversized));
            self::fail('Oversized evidence must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Audit evidence contains unsupported public fields.', $exception->getMessage());
        }
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    public function test_cross_family_due_work_survives_lost_event_and_email_change_claim_outcomes(): void
    {
        [$change, $credential] = $this->grant();
        $activation = ActivationGrant::issue($this->userId, ActivationCredential::fromString(bin2hex(random_bytes(32))),
            $this->now, $this->now->modify('+1 hour'), EmailAddress::fromString('audit@example.test'), 'encrypted-activation');
        $reset = PasswordResetGrant::issue($this->userId, PasswordResetCredential::fromString(bin2hex(random_bytes(32))),
            $this->now, $this->now->modify('+1 hour'), EmailAddress::fromString('audit@example.test'), 'encrypted-reset');
        $activations = new PostgresActivationGrantRepository($this->connection);
        $resets = new PostgresPasswordResetGrantRepository($this->connection);
        $this->commit(function () use ($change, $activation, $reset, $activations, $resets): void {
            self::assertTrue($this->changes->add($change));
            self::assertTrue($activations->add($activation));
            self::assertTrue($resets->add($reset));
            $this->audit->add(AuditEvidence::record('operator', 'user.invited', $this->userId));
        });
        $finder = new FindDueCredentialDeliveriesHandler($activations, $resets, $this->changes);
        // A new repository/connection observes committed work without a post-commit event.
        $other = DriverManager::getConnection($this->connection->getParams());
        try {
            $restarted = new FindDueCredentialDeliveriesHandler(new PostgresActivationGrantRepository($other),
                new PostgresPasswordResetGrantRepository($other), new PostgresEmailChangeGrantRepository($other));
            $due = $restarted->handle(QueryMessage::create(new FindDueCredentialDeliveries($this->now, 3)));
            self::assertCount(3, $due);
            $purposes = array_map(static fn($item): string => $item->getPurpose(), $due);
            sort($purposes);
            self::assertSame(['activation', 'email_change', 'password_reset'], $purposes);
            self::assertCount(1, $finder->handle(QueryMessage::create(new FindDueCredentialDeliveries($this->now, 1))));
            foreach ($due as $item) {
                self::assertSame($this->userId->toString(), $item->getUserId()->toString());
                self::assertSame(0, $item->getRevision());
                self::assertSame(CredentialDeliveryStatus::PENDING, $item->getStatus());
            }
        } finally {
            $other->close();
        }
        $loaded = $this->changes->getByDeliveryId($change->getDelivery()->getId());
        self::assertNotNull($loaded);
        self::assertTrue($loaded->matchesCredential($credential));
        $token = CredentialDeliveryClaimToken::generate();
        $claimed = $loaded->claimDelivery($token, $this->now, $this->now->modify('+5 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($loaded, $claimed)));
        self::assertFalse($this->commit(fn(): bool => $this->changes->replace($loaded, $claimed)));
        self::assertSame([], $this->changes->findDue($this->now, 3));
        self::assertCount(1, $this->changes->findDue($this->now->modify('+5 minutes'), 3));
        $retry = $claimed->failDelivery($token, $this->now->modify('+1 minute'), CredentialDeliveryFailure::UNEXPECTED_PROVIDER);
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($claimed, $retry)));
        self::assertEquals($this->now->modify('+2 minutes'), $retry->getDelivery()->getDueAt());
        $nextToken = CredentialDeliveryClaimToken::generate();
        $next = $retry->claimDelivery($nextToken, $retry->getDelivery()->getDueAt(),
            $retry->getDelivery()->getDueAt()->modify('+5 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($retry, $next)));
        self::assertFalse($this->commit(fn(): bool => $this->changes->replace($claimed, $claimed->confirmDelivery($token, $this->now->modify('+1 minute')))));
        $delivered = $next->confirmDelivery($nextToken, $next->getDelivery()->getClaimedAt());
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($next, $delivered)));
        self::assertNull($this->changes->getLatestByUserId($this->userId)?->getDelivery()->getEncryptedMaterial());
        self::assertSame([], $this->changes->findDue($this->now->modify('+10 minutes'), 3));
        $row = $this->connection->fetchAssociative('SELECT * FROM email_change_grants WHERE id = ?', [$change->getId()->toString()]);
        self::assertIsArray($row);
        self::assertNotContains($credential->toString(), array_values($row));
        self::assertNull($row['delivery_ciphertext']);
    }

    public function test_email_change_terminal_append_and_competing_claim(): void
    {
        [$first, $raw] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->changes->add($first)));
        [$successor] = $this->grant();
        self::assertFalse($this->commit(fn(): bool => $this->changes->appendAfterTerminal($first, $successor)));
        $terminal = $first->expireAt($this->now->modify('+1 hour'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($first, $terminal)));
        $reused = EmailChangeGrant::issue($this->userId, $raw, $this->now,
            $this->now->modify('+1 hour'), EmailAddress::fromString('new@example.test'), 'encrypted-email-change');
        self::assertFalse($this->commit(fn(): bool => $this->changes->appendAfterTerminal($terminal, $reused)));
        self::assertTrue($this->commit(fn(): bool => $this->changes->appendAfterTerminal($terminal, $successor)));
        self::assertFalse($this->commit(fn(): bool => $this->changes->appendAfterTerminal($terminal, $this->grant()[0])));
        self::assertSame($successor->getId()->toString(), $this->changes->getLatestByUserId($this->userId)?->getId()->toString());
        $other = DriverManager::getConnection($this->connection->getParams());
        $competing = new PostgresEmailChangeGrantRepository($other);
        $this->connection->beginTransaction();
        $other->beginTransaction();
        $other->executeStatement("SET LOCAL lock_timeout = '100ms'");
        try {
            $claimed = $successor->claimDelivery(CredentialDeliveryClaimToken::generate(), $this->now, $this->now->modify('+5 minutes'));
            self::assertTrue($this->changes->replace($successor, $claimed));
            try {
                $competing->replace($successor, $successor->claimDelivery(CredentialDeliveryClaimToken::generate(),
                    $this->now, $this->now->modify('+5 minutes')));
                self::fail('A competing claim must wait for the user fence.');
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
        self::assertFalse($this->commit(fn(): bool => $this->changes->replace($successor,
            $successor->claimDelivery(CredentialDeliveryClaimToken::generate(), $this->now, $this->now->modify('+5 minutes')))));
    }

    public function test_session_audit_context_is_exact_bounded_and_append_only(): void
    {
        $session = RefreshSessionId::generate();
        $reason = SessionRevocationReason::fromString('Approved device review');
        $evidence = AuditEvidence::administrativeSessionRevocation($this->userId, $this->userId, $session, $reason);
        $this->commit(fn() => $this->audit->add($evidence));
        $row = $this->connection->fetchAssociative('SELECT * FROM audit_evidence');
        self::assertIsArray($row);
        self::assertSame($this->userId->toString(), $row['actor_id']);
        self::assertSame($evidence->action(), $row['action']);
        self::assertSame(['reason' => $reason->toString(), 'refresh_session_id' => $session->toString()],
            json_decode((string) $row['context'], true, flags: JSON_THROW_ON_ERROR));
        $this->commit(fn() => $this->audit->add($evidence));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        $unicodeReason = SessionRevocationReason::fromString(str_repeat('🙂', 500));
        $this->commit(fn() => $this->audit->add(AuditEvidence::administrativeSessionRevocation(
            $this->userId, $this->userId, RefreshSessionId::generate(), $unicodeReason)));
        self::assertSame($unicodeReason->toString(), (string) $this->connection->fetchOne(
            "SELECT context->>'reason' FROM audit_evidence ORDER BY id DESC LIMIT 1"));
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    public function test_email_change_expected_state_and_terminal_outcomes_are_purpose_separated(): void
    {
        [$grant, $raw] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->changes->add($grant)));
        self::assertFalse($this->commit(fn(): bool => $this->changes->add($grant)));
        self::assertSame([], $this->changes->findDue($this->now, 0));
        $forged = $grant->claimDelivery(CredentialDeliveryClaimToken::generate(), $this->now,
            $this->now->modify('+5 minutes'));
        self::assertFalse($this->commit(fn(): bool => $this->changes->replace($forged, $forged->confirmDelivery(
            $forged->getDelivery()->getClaimToken(), $this->now))));
        $claimToken = CredentialDeliveryClaimToken::generate();
        $claimed = $grant->claimDelivery($claimToken, $this->now, $this->now->modify('+5 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($grant, $claimed)));
        try {
            $claimed->confirmDelivery($claimToken, $this->now->modify('+5 minutes'));
            self::fail('The package must reject an expired claim.');
        } catch (\Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Exception\CredentialDeliveryTransitionException) {
            self::assertTrue(true);
        }
        $nextToken = CredentialDeliveryClaimToken::generate();
        $reclaimed = $claimed->claimDelivery($nextToken, $this->now->modify('+5 minutes'),
            $this->now->modify('+10 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($claimed, $reclaimed)));
        self::assertFalse($this->commit(fn(): bool => $this->changes->replace($claimed,
            $claimed->confirmDelivery($claimToken, $this->now->modify('+4 minutes')))));
        $failed = $reclaimed->failDeliveryPermanently($nextToken, $this->now->modify('+6 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($reclaimed, $failed)));
        self::assertSame(CredentialDeliveryStatus::PERMANENT_FAILURE,
            $this->changes->getLatestByUserId($this->userId)?->getDelivery()->getStatus());
        self::assertNull($this->changes->getLatestByUserId($this->userId)?->getDelivery()->getEncryptedMaterial());
        self::assertSame([], $this->changes->findDue($this->now->modify('+10 minutes'), 5));
        $row = $this->connection->fetchAssociative('SELECT * FROM email_change_grants WHERE id = ?', [$grant->getId()->toString()]);
        self::assertIsArray($row);
        self::assertNotContains($raw->toString(), array_values($row));
    }

    /**
     * @return array{EmailChangeGrant, EmailChangeCredential}
     */
    private function grant(): array
    {
        $credential = EmailChangeCredential::fromString(bin2hex(random_bytes(32)));

        return [EmailChangeGrant::issue($this->userId, $credential, $this->now, $this->now->modify('+1 hour'),
            EmailAddress::fromString('new@example.test'), 'encrypted-email-change'), $credential];
    }

    private function commit(callable $work): mixed
    {
        return $this->unit->commitTransactional($work);
    }

    private function date(DateTimeImmutable $at): string
    {
        return $at->format('Y-m-d H:i:s.uP');
    }
}
