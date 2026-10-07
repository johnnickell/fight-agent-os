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
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentId;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Exception\CredentialDeliveryTransitionException;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindExpiredCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\Command\ExpireEmailChange;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeCredential;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrant;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\SessionRevocationReason;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class AuditDeliveryPersistenceTest
 */
final class AuditDeliveryPersistenceTest extends TestCase
{
    private Connection $connection;
    private PostgresEmailChangeGrantRepository $changes;
    private PostgresAuditEvidenceRepository $audit;
    private DoctrineTransactionalUnitOfWork $unit;
    private UserId $userId;
    private DateTimeImmutable $now;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $url = getenv('TEST_DATABASE_URL');
        $host = getenv('TEST_DATABASE_ALLOWED_HOST');
        self::assertIsString($url);
        self::assertIsString($host);
        $guard = new DatabaseTargetGuard(array_values(array_filter(array_map('trim', explode(',', $host)))));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection((new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ]))->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement(<<<'SQL'
TRUNCATE audit_evidence, email_change_grants, activation_grants, password_reset_grants,
    refresh_session_used_credentials, refresh_sessions, user_role_assignments,
    user_email_claims, users, role_permissions, roles, permissions CASCADE
SQL
        );
        $this->now = new DateTimeImmutable('2026-10-01T12:00:00+00:00');
        $this->userId = UserId::generate();
        $this->connection->insert('users', [
            'id'                                => $this->userId->toString(),
            'email'                             => 'audit@example.test',
            'state'                             => 'pending_activation',
            'password_hash'                     => null,
            'authentication_version'            => 1,
            'authentication_authority_revision' => 0,
            'authorization_assignment_revision' => 0,
            'pending_email_change'              => null,
            'email_change_reservation_revision' => 0,
            'canonical_email_revision'          => 0,
            'created_at'                        => $this->date($this->now),
            'updated_at'                        => $this->date($this->now)
        ]);
        $this->changes = new PostgresEmailChangeGrantRepository($this->connection);
        $this->audit = new PostgresAuditEvidenceRepository($this->connection);
        $config = ORMSetup::createAttributeMetadataConfiguration([], true);
        $config->enableNativeLazyObjects(true);
        $this->unit = new DoctrineTransactionalUnitOfWork(new EntityManager($this->connection, $config));
    }

    /**
     * Releases the fixture connection independently of garbage collection
     */
    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    /**
     * Verifies audit round trips typed subjects and rolls back with originating work
     */
    public function testAuditRoundTripsTypedSubjectsAndRollsBackWithOriginatingWork(): void
    {
        $agent = AgentId::generate();
        $this->commit(function () use ($agent): void {
            $this->audit->add(AuditEvidence::record($this->userId->toString(), 'user.invited', $this->userId));
            $this->audit->add(AuditEvidence::agentProvisioned($this->userId->toString(), $agent));
        });
        $rows = $this->connection->fetchAllAssociative(
            'SELECT actor_id, action, subject_type, subject_id, context FROM audit_evidence ORDER BY id'
        );
        self::assertCount(2, $rows);
        self::assertSame(['user', 'agent'], array_column($rows, 'subject_type'));
        self::assertSame([$this->userId->toString(), $agent->toString()], array_column($rows, 'subject_id'));
        self::assertSame(['user.invited', 'agent.provisioned'], array_column($rows, 'action'));
        self::assertSame([$this->userId->toString(), $this->userId->toString()], array_column($rows, 'actor_id'));
        self::assertSame(['{}', '{}'], array_column($rows, 'context'));
        [$grant] = $this->grant();
        try {
            $this->commit(function () use ($grant): void {
                self::assertTrue($this->changes->add($grant));
                $this->audit->add(AuditEvidence::record(
                    $this->userId->toString(),
                    'user.email_change_administratively_requested',
                    $this->userId
                ));
                throw new RuntimeException('caller rollback');
            });
            self::fail('Expected caller rollback.');
        } catch (RuntimeException $exception) {
            self::assertSame('caller rollback', $exception->getMessage());
        }
        self::assertNull($this->changes->getLatestByUserId($this->userId));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    /**
     * Verifies invalid and secret bearing context does not write
     */
    public function testInvalidAndSecretBearingContextDoesNotWrite(): void
    {
        $invalid = new class (
            $this->userId->toString(),
            'user.invited',
            $this->userId,
            ['access_token' => 'private']
        ) extends AuditEvidence {
            /**
             * Constructs an invalid audit evidence fixture
             *
             * @phpstan-param array<string, string> $context
             */
            public function __construct(string $actorId, string $action, UserId|AgentId $subjectId, array $context = [])
            {
                parent::__construct($actorId, $action, $subjectId, $context);
            }
        };
        $this->assertAuditRejected($invalid);
        $oversized = new class (str_repeat('a', 129), 'user.invited', $this->userId) extends AuditEvidence {
            /**
             * Constructs an invalid audit evidence fixture
             *
             * @phpstan-param array<string, string> $context
             */
            public function __construct(string $actorId, string $action, UserId|AgentId $subjectId, array $context = [])
            {
                parent::__construct($actorId, $action, $subjectId, $context);
            }
        };
        $this->assertAuditRejected($oversized);
        $secretActor = AuditEvidence::record('Bearer '.bin2hex(random_bytes(32)), 'user.invited', $this->userId);
        $this->assertAuditRejected($secretActor);
        $this->assertAuditRejected(AuditEvidence::record('anonymous', 'user.invited', $this->userId));
        $this->assertAuditRejected(AuditEvidence::record('credential-recovery', 'user.invited', $this->userId));
        $this->assertAuditRejected(AuditEvidence::record(
            'credential-recovery',
            'user.password_reset_requested',
            $this->userId
        ));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        $this->commit(fn() => $this->audit->add(AuditEvidence::record(
            'anonymous',
            'user.password_reset_requested',
            $this->userId
        )));
        self::assertSame('anonymous', $this->connection->fetchOne('SELECT actor_id FROM audit_evidence'));
    }

    /**
     * Verifies expiry after retry and reclaim destroys material and rejects stale outcomes
     */
    public function testExpiryAfterRetryAndReclaimDestroysMaterialAndRejectsStaleOutcomes(): void
    {
        [$grant] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->changes->add($grant)));
        $firstToken = CredentialDeliveryClaimToken::generate();
        $claimed = $grant->claimDelivery($firstToken, $this->now, $this->now->modify('+5 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($grant, $claimed)));
        $retry = $claimed->failDelivery(
            $firstToken,
            $this->now->modify('+1 minute'),
            CredentialDeliveryFailure::UNEXPECTED_PROVIDER
        );
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($claimed, $retry)));
        $dueAt = $retry->getDelivery()->getDueAt();
        $nextToken = CredentialDeliveryClaimToken::generate();
        $reclaimed = $retry->claimDelivery($nextToken, $dueAt, $dueAt->modify('+5 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($retry, $reclaimed)));
        $expired = $reclaimed->expireDeliveryAt($grant->getExpiresAt());
        self::assertSame(CredentialDeliveryStatus::EXPIRED, $expired->getDelivery()->getStatus());
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($reclaimed, $expired)));
        self::assertTrue($expired->getDelivery()->sameStateAs(
            $this->changes->getLatestByUserId($this->userId)?->getDelivery()
        ));
        self::assertNull($this->connection->fetchOne(
            'SELECT delivery_ciphertext FROM email_change_grants WHERE id = ?',
            [$grant->getId()->toString()]
        ));
        self::assertSame([], $this->changes->findDue($grant->getExpiresAt(), 10));
        self::assertFalse($this->commit(fn(): bool => $this->changes->replace(
            $reclaimed,
            $reclaimed->confirmDelivery($nextToken, $dueAt->modify('+1 minute'))
        )));
    }

    /**
     * Verifies retry backoff crossing expiry persists package terminal outcome
     */
    public function testRetryBackoffCrossingExpiryPersistsPackageTerminalOutcome(): void
    {
        [$grant] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->changes->add($grant)));
        $claimedAt = $this->now->modify('+59 minutes');
        $token = CredentialDeliveryClaimToken::generate();
        $claimed = $grant->claimDelivery($token, $claimedAt, $grant->getExpiresAt());
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($grant, $claimed)));
        $failed = $claimed->failDelivery(
            $token,
            $claimedAt->modify('+30 seconds'),
            CredentialDeliveryFailure::UNEXPECTED_PROVIDER
        );
        self::assertSame(CredentialDeliveryStatus::EXPIRED, $failed->getDelivery()->getStatus());
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($claimed, $failed)));
        self::assertTrue($failed->getDelivery()->sameStateAs(
            $this->changes->getLatestByUserId($this->userId)?->getDelivery()
        ));
        self::assertNull($this->connection->fetchOne(
            'SELECT delivery_ciphertext FROM email_change_grants WHERE id = ?',
            [$grant->getId()->toString()]
        ));
    }

    /**
     * Verifies cross family due work survives lost event and email change claim outcomes
     */
    public function testCrossFamilyDueWorkSurvivesLostEventAndEmailChangeClaimOutcomes(): void
    {
        [$change, $credential] = $this->grant();
        $activation = ActivationGrant::issue(
            $this->userId,
            ActivationCredential::fromString(bin2hex(random_bytes(32))),
            $this->now,
            $this->now->modify('+1 hour'),
            EmailAddress::fromString('audit@example.test'),
            'encrypted-activation'
        );
        $reset = PasswordResetGrant::issue(
            $this->userId,
            PasswordResetCredential::fromString(bin2hex(random_bytes(32))),
            $this->now,
            $this->now->modify('+1 hour'),
            EmailAddress::fromString('audit@example.test'),
            'encrypted-reset'
        );
        $activations = new PostgresActivationGrantRepository($this->connection);
        $resets = new PostgresPasswordResetGrantRepository($this->connection);
        $this->commit(function () use ($change, $activation, $reset, $activations, $resets): void {
            self::assertTrue($this->changes->add($change));
            self::assertTrue($activations->add($activation));
            self::assertTrue($resets->add($reset));
            $this->audit->add(AuditEvidence::record($this->userId->toString(), 'user.invited', $this->userId));
        });
        $finder = new FindDueCredentialDeliveriesHandler($activations, $resets, $this->changes);
        // A new repository/connection observes committed work without a post-commit event.
        $other = DriverManager::getConnection($this->connection->getParams());
        try {
            $restarted = new FindDueCredentialDeliveriesHandler(
                new PostgresActivationGrantRepository($other),
                new PostgresPasswordResetGrantRepository($other),
                new PostgresEmailChangeGrantRepository($other)
            );
            $due = $restarted->handle(QueryMessage::create(new FindDueCredentialDeliveries($this->now, 3)));
            self::assertCount(3, $due);
            $purposes = array_map(static fn($item): string => $item->getPurpose(), $due);
            sort($purposes);
            self::assertSame(['activation', 'email_change', 'password_reset'], $purposes);
            self::assertCount(
                1,
                $finder->handle(QueryMessage::create(new FindDueCredentialDeliveries($this->now, 1)))
            );
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
        $retry = $claimed->failDelivery(
            $token,
            $this->now->modify('+1 minute'),
            CredentialDeliveryFailure::UNEXPECTED_PROVIDER
        );
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($claimed, $retry)));
        self::assertEquals($this->now->modify('+2 minutes'), $retry->getDelivery()->getDueAt());
        $nextToken = CredentialDeliveryClaimToken::generate();
        $next = $retry->claimDelivery(
            $nextToken,
            $retry->getDelivery()->getDueAt(),
            $retry->getDelivery()->getDueAt()->modify('+5 minutes')
        );
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($retry, $next)));
        self::assertFalse($this->commit(
            fn(): bool => $this->changes->replace(
                $claimed,
                $claimed->confirmDelivery($token, $this->now->modify('+1 minute'))
            )
        ));
        $delivered = $next->confirmDelivery($nextToken, $next->getDelivery()->getClaimedAt());
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($next, $delivered)));
        self::assertNull($this->changes->getLatestByUserId($this->userId)?->getDelivery()->getEncryptedMaterial());
        self::assertSame([], $this->changes->findDue($this->now->modify('+10 minutes'), 3));
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM email_change_grants WHERE id = ?',
            [$change->getId()->toString()]
        );
        self::assertIsArray($row);
        self::assertNotContains($credential->toString(), array_values($row));
        self::assertNull($row['delivery_ciphertext']);
    }

    /**
     * Verifies email change terminal append and competing claim
     */
    public function testEmailChangeTerminalAppendAndCompetingClaim(): void
    {
        [$first, $raw] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->changes->add($first)));
        [$successor] = $this->grant();
        self::assertFalse($this->commit(fn(): bool => $this->changes->appendAfterTerminal($first, $successor)));
        $terminal = $first->expireAt($this->now->modify('+1 hour'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($first, $terminal)));
        $reused = EmailChangeGrant::issue(
            $this->userId,
            $raw,
            $this->now,
            $this->now->modify('+1 hour'),
            EmailAddress::fromString('new@example.test'),
            'encrypted-email-change',
            1
        );
        self::assertFalse($this->commit(fn(): bool => $this->changes->appendAfterTerminal($terminal, $reused)));
        self::assertTrue($this->commit(fn(): bool => $this->changes->appendAfterTerminal($terminal, $successor)));
        self::assertFalse($this->commit(
            fn(): bool => $this->changes->appendAfterTerminal($terminal, $this->grant()[0])
        ));
        self::assertSame(
            $successor->getId()->toString(),
            $this->changes->getLatestByUserId($this->userId)?->getId()->toString()
        );
        $other = DriverManager::getConnection($this->connection->getParams());
        $competing = new PostgresEmailChangeGrantRepository($other);
        $this->connection->beginTransaction();
        $other->beginTransaction();
        $other->executeStatement("SET LOCAL lock_timeout = '100ms'");
        try {
            $claimed = $successor->claimDelivery(
                CredentialDeliveryClaimToken::generate(),
                $this->now,
                $this->now->modify('+5 minutes')
            );
            self::assertTrue($this->changes->replace($successor, $claimed));
            try {
                $competing->replace($successor, $successor->claimDelivery(
                    CredentialDeliveryClaimToken::generate(),
                    $this->now,
                    $this->now->modify('+5 minutes')
                ));
                self::fail('A competing claim must wait for the user fence.');
            } catch (DriverException $exception) {
                self::assertSame('55P03', $exception->getSQLState());
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
        self::assertFalse($this->commit(fn(): bool => $this->changes->replace(
            $successor,
            $successor->claimDelivery(
                CredentialDeliveryClaimToken::generate(),
                $this->now,
                $this->now->modify('+5 minutes')
            )
        )));
    }

    /**
     * Verifies session audit context is exact bounded and append only
     */
    public function testSessionAuditContextIsExactBoundedAndAppendOnly(): void
    {
        $session = RefreshSessionId::generate();
        $reason = SessionRevocationReason::fromString('Approved device review');
        $evidence = AuditEvidence::administrativeSessionRevocation($this->userId, $this->userId, $session, $reason);
        $this->commit(fn() => $this->audit->add($evidence));
        $row = $this->connection->fetchAssociative('SELECT * FROM audit_evidence');
        self::assertIsArray($row);
        self::assertSame($this->userId->toString(), $row['actor_id']);
        self::assertSame($evidence->action(), $row['action']);
        self::assertSame(
            ['reason' => $reason->toString(), 'refresh_session_id' => $session->toString()],
            json_decode((string) $row['context'], true, flags: JSON_THROW_ON_ERROR)
        );
        $this->commit(fn() => $this->audit->add($evidence));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        $unicodeReason = SessionRevocationReason::fromString(str_repeat('🙂', 500));
        $this->commit(fn() => $this->audit->add(AuditEvidence::administrativeSessionRevocation(
            $this->userId,
            $this->userId,
            RefreshSessionId::generate(),
            $unicodeReason
        )));
        self::assertSame($unicodeReason->toString(), (string) $this->connection->fetchOne(
            "SELECT context->>'reason' FROM audit_evidence ORDER BY id DESC LIMIT 1"
        ));
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    /**
     * Verifies email change expected state and terminal outcomes are purpose separated
     */
    public function testEmailChangeExpectedStateAndTerminalOutcomesArePurposeSeparated(): void
    {
        [$grant, $raw] = $this->grant();
        self::assertTrue($this->commit(fn(): bool => $this->changes->add($grant)));
        self::assertFalse($this->commit(fn(): bool => $this->changes->add($grant)));
        self::assertSame([], $this->changes->findDue($this->now, 0));
        [$otherGeneration] = $this->grant();
        self::assertFalse($this->commit(fn(): bool => $this->changes->replace($grant, $otherGeneration)));
        $forged = $grant->claimDelivery(
            CredentialDeliveryClaimToken::generate(),
            $this->now,
            $this->now->modify('+5 minutes')
        );
        self::assertFalse($this->commit(fn(): bool => $this->changes->replace($forged, $forged->confirmDelivery(
            $forged->getDelivery()->getClaimToken(),
            $this->now
        ))));
        $claimToken = CredentialDeliveryClaimToken::generate();
        $claimed = $grant->claimDelivery($claimToken, $this->now, $this->now->modify('+5 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($grant, $claimed)));
        try {
            $claimed->confirmDelivery($claimToken, $this->now->modify('+5 minutes'));
            self::fail('The package must reject an expired claim.');
        } catch (CredentialDeliveryTransitionException) {
            // The later adapter assertions verify that this rejection left no persisted transition.
        }
        $nextToken = CredentialDeliveryClaimToken::generate();
        $reclaimed = $claimed->claimDelivery(
            $nextToken,
            $this->now->modify('+5 minutes'),
            $this->now->modify('+10 minutes')
        );
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($claimed, $reclaimed)));
        self::assertFalse($this->commit(fn(): bool => $this->changes->replace(
            $claimed,
            $claimed->confirmDelivery($claimToken, $this->now->modify('+4 minutes'))
        )));
        $failed = $reclaimed->failDeliveryPermanently($nextToken, $this->now->modify('+6 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($reclaimed, $failed)));
        self::assertSame(
            CredentialDeliveryStatus::PERMANENT_FAILURE,
            $this->changes->getLatestByUserId($this->userId)->getDelivery()->getStatus()
        );
        self::assertNull($this->changes->getLatestByUserId($this->userId)?->getDelivery()->getEncryptedMaterial());
        self::assertSame([], $this->changes->findDue($this->now->modify('+10 minutes'), 5));
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM email_change_grants WHERE id = ?',
            [$grant->getId()->toString()]
        );
        self::assertIsArray($row);
        self::assertNotContains($raw->toString(), array_values($row));
        $consumed = $failed->consume($this->now->modify('+7 minutes'));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($failed, $consumed)));
        self::assertTrue($this->changes->getLatestByUserId($this->userId)?->isConsumed());
        self::assertFalse($this->changes->getLatestByUserId($this->userId)->getDelivery()->hasRecoverableMaterial());
    }

    /**
     * Stores delivery-only expiry from provider backoff without losing its terminal failure history
     */
    public function testEmailDeliveryBackoffCrossingExpiryRoundTrips(): void
    {
        [$grant] = $this->grant();
        $at = $grant->getExpiresAt()->modify('-1 second');
        $token = CredentialDeliveryClaimToken::generate();
        $claimed = $grant->claimDelivery($token, $at, $grant->getExpiresAt());
        self::assertFalse($this->commit(fn(): bool => $this->changes->add($claimed)));
        self::assertTrue($this->commit(fn(): bool => $this->changes->add($grant)));
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($grant, $claimed)));
        $expired = $claimed->failDelivery($token, $at, CredentialDeliveryFailure::RETRYABLE_PROVIDER);
        self::assertTrue($this->commit(fn(): bool => $this->changes->replace($claimed, $expired)));
        $stored = $this->changes->getByDeliveryId($grant->getDelivery()->getId());
        self::assertNotNull($stored);
        self::assertSame(CredentialDeliveryStatus::EXPIRED, $stored->getDelivery()->getStatus());
        self::assertSame(CredentialDeliveryFailure::RETRYABLE_PROVIDER, $stored->getDelivery()->getLastFailure());
        self::assertEquals($at, $stored->getDelivery()->getLastOutcomeAt());
        self::assertFalse($stored->getDelivery()->hasRecoverableMaterial());
        self::assertTrue($stored->isIssued());
        $this->expectException(\LogicException::class);
        $this->changes->replace($claimed, $expired);
    }

    /**
     * Expires email authority even after terminal delivery or account-state changes without changing security state
     */
    #[DataProvider('emailExpiryStates')]
    public function testEmailExpiryRetainsDeliveryHistoryAndAccountState(string $state, string $deliveryState): void
    {
        [$grant] = $this->grant();
        $this->seedReservation($state, 1);
        self::assertTrue($this->commit(fn(): bool => $this->changes->add($grant)));
        if ($deliveryState !== 'pending') {
            $claim = $grant->claimDelivery(
                CredentialDeliveryClaimToken::generate(),
                $this->now,
                $this->now->modify('+5 minutes')
            );
            self::assertTrue($this->commit(fn(): bool => $this->changes->replace($grant, $claim)));
            $terminal = match ($deliveryState) {
                'delivered' => $claim->confirmDelivery($claim->getDelivery()->getClaimToken(), $this->now),
                'permanent_failure' => $claim->failDeliveryPermanently(
                    $claim->getDelivery()->getClaimToken(),
                    $this->now
                ),
                'expired' => $claim->expireDeliveryAt($this->now->modify('+1 hour')),
                default => throw new InvalidArgumentException('Unsupported fixture state.')
            };
            self::assertTrue($this->commit(fn(): bool => $this->changes->replace($claim, $terminal)));
        }
        $before = $this->connection->fetchAssociative('SELECT * FROM users WHERE id = ?', [$this->userId->toString()]);
        self::assertIsArray($before);
        $stored = $this->changes->getLatestByUserId($this->userId);
        self::assertSame(1, $stored->getEmailChangeReservationRevision());
        $container = $this->expiryContainer();
        $at = $this->now->modify('+1 hour');
        $query = new FindExpiredCredentialDeliveries($at, 1);
        $work = $container->get(QueryBus::class)->fetch($query);
        self::assertCount(1, $work);
        self::assertSame($grant->getId()->toString(), $work[0]->getEmailChangeGrantId()->toString());
        $command = new ExpireEmailChange('credential-recovery', $this->userId, $grant->getId(), $at);
        $container->get(CommandBus::class)->execute($command);
        $after = $this->connection->fetchAssociative('SELECT * FROM users WHERE id = ?', [$this->userId->toString()]);
        self::assertIsArray($after);
        self::assertNull($after['pending_email_change']);
        self::assertSame(2, (int) $after['email_change_reservation_revision']);
        foreach ($before as $field => $value) {
            if (!in_array($field, ['pending_email_change', 'email_change_reservation_revision', 'updated_at'], true)) {
                self::assertSame($value, $after[$field], $field);
            }
        }
        self::assertSame(0, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM user_email_claims WHERE claim_type = 'reservation'"
        ));
        $expired = $this->changes->getLatestByUserId($this->userId);
        self::assertTrue($expired->isExpired());
        self::assertNull($expired->getDelivery()->getEncryptedMaterial());
        self::assertSame(
            $deliveryState === 'pending' ? 'invalidated' : $deliveryState,
            $expired->getDelivery()->getStatus()->value
        );
        self::assertSame($stored->getDelivery()->getAttemptCount(), $expired->getDelivery()->getAttemptCount());
        self::assertEquals($stored->getDelivery()->getLastOutcomeAt(), $expired->getDelivery()->getLastOutcomeAt());
        self::assertSame([], $container->get(QueryBus::class)->fetch($query));
        $container->get(CommandBus::class)->execute($command);
        self::assertSame($expired->getRevision(), $this->changes->getLatestByUserId($this->userId)->getRevision());
    }

    /**
     * Covers reachable account and material-free delivery combinations
     *
     * @return iterable<array{string, string}>
     */
    public static function emailExpiryStates(): iterable
    {
        yield ['active', 'pending'];
        yield ['disabled', 'delivered'];
        yield ['deleted', 'permanent_failure'];
        yield ['pending_activation', 'expired'];
    }

    /**
     * Refuses a same-email newer reservation rather than clearing unrelated authority
     */
    public function testEmailExpiryRejectsReservationAba(): void
    {
        [$grant] = $this->grant();
        $this->seedReservation('active', 2);
        self::assertTrue($this->commit(fn(): bool => $this->changes->add($grant)));
        $container = $this->expiryContainer();
        try {
            $container->get(CommandBus::class)->execute(new ExpireEmailChange(
                'credential-recovery',
                $this->userId,
                $grant->getId(),
                $this->now->modify('+1 hour')
            ));
            self::fail('A newer reservation cannot be cleared by the old grant.');
        } catch (\LogicException) {
            self::assertSame(0, $this->changes->getLatestByUserId($this->userId)->getRevision());
            self::assertSame('new@example.test', $this->connection->fetchOne('SELECT pending_email_change FROM users'));
            self::assertCount(1, $this->changes->findExpired($this->now->modify('+1 hour'), 1));
        }
    }

    /**
     * Rolls back reservation clearing if the coupled grant expiry cannot commit
     */
    public function testEmailExpiryRollsBackBothSidesAfterGrantWriteFailure(): void
    {
        [$grant] = $this->grant();
        $this->seedReservation('active', 1);
        self::assertTrue($this->commit(fn(): bool => $this->changes->add($grant)));
        $container = $this->expiryContainer();
        $this->connection->executeStatement(<<<'SQL'
ALTER TABLE email_change_grants ADD CONSTRAINT expiry_test_abort CHECK (expired_at IS NULL) NOT VALID
SQL);
        $command = new ExpireEmailChange(
            'credential-recovery',
            $this->userId,
            $grant->getId(),
            $this->now->modify('+1 hour')
        );
        try {
            $container->get(CommandBus::class)->execute($command);
            self::fail('The grant failure must roll back the reservation write.');
        } catch (DriverException) {
            self::assertSame('new@example.test', $this->connection->fetchOne('SELECT pending_email_change FROM users'));
            self::assertSame(1, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM user_email_claims WHERE claim_type = 'reservation'"
            ));
            self::assertSame(0, $this->changes->getLatestByUserId($this->userId)->getRevision());
        } finally {
            $this->connection->executeStatement('ALTER TABLE email_change_grants DROP CONSTRAINT expiry_test_abort');
        }
        $this->expiryContainer()->get(CommandBus::class)->execute($command);
        self::assertTrue($this->changes->getLatestByUserId($this->userId)->isExpired());
        self::assertNull($this->connection->fetchOne('SELECT pending_email_change FROM users'));
    }

    /**
     * Seeds a bound reservation in a reachable account state
     */
    private function seedReservation(string $state, int $revision): void
    {
        $hash = $state === 'pending_activation' ? null : password_hash('fixture-only', PASSWORD_ARGON2ID);
        $this->connection->update('users', [
            'state'                             => $state,
            'password_hash'                     => $hash,
            'pending_email_change'              => 'new@example.test',
            'email_change_reservation_revision' => $revision
        ], ['id' => $this->userId->toString()]);
        $this->connection->insert('user_email_claims', [
            'user_id' => $this->userId->toString(), 'claim_type' => 'reservation', 'email' => 'new@example.test'
        ]);
    }

    /**
     * Opens cleanup composition on the same connection without providers or ciphers
     */
    private function expiryContainer(): Container
    {
        $container = require dirname(__DIR__, 3).'/config/services.php';
        $container->set(Connection::class, fn(): Connection => $this->connection);

        return $container;
    }

    /**
     * @phpstan-return array{EmailChangeGrant, EmailChangeCredential}
     */
    private function grant(): array
    {
        $credential = EmailChangeCredential::fromString(bin2hex(random_bytes(32)));

        return [EmailChangeGrant::issue(
            $this->userId,
            $credential,
            $this->now,
            $this->now->modify('+1 hour'),
            EmailAddress::fromString('new@example.test'),
            'encrypted-email-change',
            1
        ), $credential];
    }

    /**
     * Verifies the rejected audit record leaves no persisted evidence
     */
    private function assertAuditRejected(AuditEvidence $evidence): void
    {
        $this->connection->beginTransaction();
        try {
            $this->audit->add($evidence);
            self::fail('Unsupported evidence must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Audit evidence contains unsupported public fields.', $exception->getMessage());
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Runs the operation within a test transaction
     */
    private function commit(callable $work): mixed
    {
        return $this->unit->commitTransactional($work);
    }

    /**
     * Formats a date for PostgreSQL
     */
    private function date(DateTimeImmutable $at): string
    {
        return $at->format('Y-m-d H:i:s.uP');
    }
}
