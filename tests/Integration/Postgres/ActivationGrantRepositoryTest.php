<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\ActivationGrantRecords;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Hydration\PersistedActivationGrant;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\PersistenceConflict;
use App\Adapter\Persistence\Repository\PostgresActivationGrantRepository;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantId;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class ActivationGrantRepositoryTest
 */
final class ActivationGrantRepositoryTest extends TestCase
{
    private Connection $connection;
    private DoctrineTransactionalUnitOfWork $unitOfWork;
    private PostgresActivationGrantRepository $grants;
    private PostgresUserRepository $users;
    private DateTimeImmutable $issuedAt;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('TRUNCATE activation_grants, users CASCADE');
        $this->grants = new PostgresActivationGrantRepository($this->connection);
        $this->users = new PostgresUserRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection),
            new AuthenticationAuthorityFences($this->connection)
        );
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork(new EntityManager($this->connection, $configuration));
        $this->issuedAt = new DateTimeImmutable('2026-09-26T12:00:00+00:00');
    }

    /**
     * Verifies secret safe round trip and absence queries
     */
    public function testSecretSafeRoundTripAndAbsenceQueries(): void
    {
        $user = $this->user();
        [$grant, $credential] = $this->grant($user->getId());
        self::assertNull($this->grants->getById($grant->getId()));
        self::assertNull($this->grants->getByDeliveryId($grant->getDelivery()->getId()));
        self::assertNull($this->grants->getLatestByUserId($user->getId()));
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($grant)));
        foreach (
            [$this->grants->getById($grant->getId()),
                  $this->grants->getByDeliveryId($grant->getDelivery()->getId()),
                  $this->grants->getLatestByUserId($user->getId())] as $stored
        ) {
            self::assertNotNull($stored);
            self::assertTrue(ActivationGrantRecords::same($grant, $stored));
            self::assertTrue($stored->matchesCredential($credential));
        }
        self::assertNull($this->grants->getById(ActivationGrantId::generate()));
        self::assertNull($this->grants->getByDeliveryId(ActivationDeliveryId::generate()));
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM activation_grants WHERE id = ?',
            [$grant->getId()->toString()]
        );
        self::assertIsArray($row);
        self::assertSame(hash('sha256', $credential->toString()), $row['credential_digest']);
        self::assertNotContains($credential->toString(), array_values($row));
        self::assertNotEmpty($row['delivery_ciphertext']);
    }

    /**
     * Verifies due discovery only returns latest eligible generations in stable order
     */
    public function testDueDiscoveryOnlyReturnsLatestEligibleGenerationsInStableOrder(): void
    {
        $user = $this->user();
        [$first] = $this->grant($user->getId());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($first)));
        $other = $this->user('other@example.test');
        [$second] = $this->grant($other->getId());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($second)));
        self::assertSame([], $this->grants->findDue($this->issuedAt->modify('-1 second'), 10));
        self::assertSame([], $this->grants->findDue($this->issuedAt, 0));
        $due = $this->grants->findDue($this->issuedAt, 1);
        self::assertCount(1, $due);
        self::assertSame('activation', $due[0]->getPurpose());
        self::assertSame(CredentialDeliveryStatus::PENDING, $due[0]->getStatus());
        self::assertSame(0, $due[0]->getRevision());
        $ids = [$first->getDelivery()->getId()->toString(), $second->getDelivery()->getId()->toString()];
        sort($ids);
        self::assertSame($ids[0], $due[0]->getDeliveryId()->toString());

        $terminal = $first->revoke($this->issuedAt->modify('+1 minute'));
        [$successor] = $this->grant($user->getId(), null, $this->issuedAt->modify('+2 minutes'));
        self::assertTrue($this->transaction(
            fn(): bool => $this->grants->replaceWithSuccessor($first, $terminal, $successor)
        ));
        self::assertSame(
            $successor->getId()->toString(),
            $this->grants->getLatestByUserId($user->getId())?->getId()->toString()
        );
        self::assertSame(
            $first->getId()->toString(),
            $this->grants->getByDeliveryId($first->getDelivery()->getId())?->getId()->toString()
        );
        $due = $this->grants->findDue($this->issuedAt, 10);
        self::assertCount(1, $due);
        self::assertSame($second->getDelivery()->getId()->toString(), $due[0]->getDeliveryId()->toString());
    }

    /**
     * Verifies claim retry and terminal delivery round trip with stale callback rejection
     */
    public function testClaimRetryAndTerminalDeliveryRoundTripWithStaleCallbackRejection(): void
    {
        $user = $this->user();
        [$first] = $this->grant($user->getId());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($first)));
        $claimAt = $this->issuedAt->modify('+1 minute');
        $token = CredentialDeliveryClaimToken::generate();
        $claimed = $first->claimDelivery($token, $claimAt, $claimAt->modify('+5 minutes'));
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($first, $claimed)));
        self::assertFalse($this->transaction(fn(): bool => $this->grants->replace($first, $claimed)));
        self::assertTrue(ActivationGrantRecords::same($claimed, $this->grants->getById($first->getId())));
        self::assertSame([], $this->grants->findDue($claimAt, 10));
        self::assertCount(1, $this->grants->findDue($claimAt->modify('+5 minutes'), 10));
        $failed = $claimed->failDelivery(
            $token,
            $claimAt->modify('+2 minutes'),
            CredentialDeliveryFailure::RETRYABLE_PROVIDER
        );
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($claimed, $failed)));
        self::assertTrue(ActivationGrantRecords::same($failed, $this->grants->getById($first->getId())));
        $retried = $failed->requestDeliveryRetry();
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($failed, $retried)));
        self::assertFalse($this->transaction(fn(): bool => $this->grants->replace(
            $claimed,
            $claimed->confirmDelivery($token, $claimAt->modify('+1 minute'))
        )));
        $reclaimed = $retried->claimDelivery(
            CredentialDeliveryClaimToken::generate(),
            $claimAt->modify('+3 minutes'),
            $claimAt->modify('+8 minutes')
        );
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($retried, $reclaimed)));
        $delivered = $reclaimed->confirmDelivery(
            $reclaimed->getDelivery()->getClaimToken(),
            $claimAt->modify('+4 minutes')
        );
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($reclaimed, $delivered)));
        self::assertFalse($this->grants->getById($first->getId())?->getDelivery()->hasRecoverableMaterial());
    }

    /**
     * Verifies consumption and permanent or expired delivery are terminal and secret free
     */
    public function testConsumptionAndPermanentOrExpiredDeliveryAreTerminalAndSecretFree(): void
    {
        $user = $this->user();
        [$first] = $this->grant($user->getId());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($first)));
        $claimAt = $this->issuedAt->modify('+1 minute');
        $token = CredentialDeliveryClaimToken::generate();
        $claimed = $first->claimDelivery($token, $claimAt, $claimAt->modify('+5 minutes'));
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($first, $claimed)));
        $permanent = $claimed->failDeliveryPermanently($token, $claimAt->modify('+1 minute'));
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($claimed, $permanent)));
        self::assertTrue(ActivationGrantRecords::same($permanent, $this->grants->getById($first->getId())));
        self::assertFalse($this->grants->getById($first->getId())?->getDelivery()->hasRecoverableMaterial());
        $consumed = $permanent->consume($claimAt->modify('+2 minutes'));
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($permanent, $consumed)));
        self::assertTrue(ActivationGrantRecords::same($consumed, $this->grants->getById($first->getId())));
        self::assertSame([], $this->grants->findDue($claimAt->modify('+6 minutes'), 10));

        [$next] = $this->grant($user->getId());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->addSuccessor($next)));
        $expired = $next->expireDeliveryAt($next->getExpiresAt());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($next, $expired)));
        self::assertTrue(ActivationGrantRecords::same($expired, $this->grants->getLatestByUserId($user->getId())));
        self::assertSame([], $this->grants->findDue($next->getExpiresAt(), 10));
    }

    /**
     * Verifies duplicate history and invalid successors leave authoritative state unchanged
     */
    public function testDuplicateHistoryAndInvalidSuccessorsLeaveAuthoritativeStateUnchanged(): void
    {
        $user = $this->user();
        [$first, $firstCredential] = $this->grant($user->getId());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($first)));
        self::assertFalse($this->transaction(fn(): bool => $this->grants->add($first)));
        [$other] = $this->grant($user->getId());
        self::assertFalse($this->transaction(fn(): bool => $this->grants->add($other)));
        self::assertFalse($this->transaction(fn(): bool => $this->grants->addSuccessor($other)));
        $terminal = $first->revoke($this->issuedAt->modify('+1 minute'));
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($first, $terminal)));
        self::assertFalse($this->transaction(
            fn(): bool => $this->grants->replaceWithSuccessor($first, $terminal, $other)
        ));
        [$reused] = $this->grant($user->getId(), $firstCredential);
        self::assertFalse($this->transaction(fn(): bool => $this->grants->addSuccessor($reused)));
        self::assertFalse($this->transaction(fn(): bool => $this->grants->addSuccessor($first)));
        $otherUser = $this->user('elsewhere@example.test');
        [$foreign] = $this->grant($otherUser->getId());
        self::assertFalse($this->transaction(
            fn(): bool => $this->grants->replaceWithSuccessor($terminal, $terminal, $foreign)
        ));
        self::assertTrue($this->transaction(fn(): bool => $this->grants->addSuccessor($other)));
        self::assertFalse($this->transaction(fn(): bool => $this->grants->addSuccessor($reused)));
        self::assertSame(2, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM activation_grants WHERE user_id = ?',
            [$user->getId()->toString()]
        ));
        self::assertSame(
            $other->getId()->toString(),
            $this->grants->getLatestByUserId($user->getId())?->getId()->toString()
        );
        self::assertSame($first->getId()->toString(), $this->connection->fetchOne(
            'SELECT predecessor_id FROM activation_grants WHERE id = ?',
            [$other->getId()->toString()]
        ));
    }

    /**
     * Verifies predecessor and successor rollback together on failure and caller rollback
     */
    public function testPredecessorAndSuccessorRollbackTogetherOnFailureAndCallerRollback(): void
    {
        $user = $this->user();
        [$first, $firstCredential] = $this->grant($user->getId());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($first)));
        $terminal = $first->revoke($this->issuedAt->modify('+1 minute'));
        [$colliding] = $this->grant($user->getId(), $firstCredential);
        self::assertFalse($this->transaction(
            fn(): bool => $this->grants->replaceWithSuccessor($first, $terminal, $colliding)
        ));
        self::assertTrue(ActivationGrantRecords::same($first, $this->grants->getById($first->getId())));
        self::assertNull($this->grants->getById($colliding->getId()));
        [$successor] = $this->grant($user->getId());
        try {
            $this->transaction(function () use ($first, $terminal, $successor): void {
                self::assertTrue($this->grants->replaceWithSuccessor($first, $terminal, $successor));
                throw new RuntimeException('Injected caller rollback');
            });
            self::fail('A caller rollback must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected caller rollback', $exception->getMessage());
        }
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork(new EntityManager($this->connection, $configuration));
        self::assertTrue(ActivationGrantRecords::same($first, $this->grants->getById($first->getId())));
        self::assertNull($this->grants->getById($successor->getId()));
        self::assertTrue($this->transaction(
            fn(): bool => $this->grants->replaceWithSuccessor($first, $terminal, $successor)
        ));
        self::assertTrue(ActivationGrantRecords::same($terminal, $this->grants->getById($first->getId())));
        self::assertSame($first->getId()->toString(), $this->connection->fetchOne(
            'SELECT predecessor_id FROM activation_grants WHERE id = ?',
            [$successor->getId()->toString()]
        ));
    }

    /**
     * Verifies competing writes wait for the user fence and only one predecessor wins
     */
    public function testCompetingWritesWaitForTheUserFenceAndOnlyOnePredecessorWins(): void
    {
        $user = $this->user();
        [$first] = $this->grant($user->getId());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($first)));
        $winner = $first->revoke($this->issuedAt->modify('+1 minute'));
        $loser = $first->consume($this->issuedAt->modify('+2 minutes'));
        [$successor] = $this->grant($user->getId());
        $competitor = $this->connection();
        $otherRepo = new PostgresActivationGrantRepository($competitor);
        $this->connection->beginTransaction();
        $competitor->beginTransaction();
        $competitor->executeStatement("SET LOCAL lock_timeout = '100ms'");
        try {
            self::assertTrue($this->grants->replaceWithSuccessor($first, $winner, $successor));
            try {
                $otherRepo->replace($first, $loser);
                self::fail('Concurrent activation must wait for the grant fence.');
            } catch (DriverException $exception) {
                self::assertSame('55P03', $exception->getSQLState());
            }
            $this->connection->commit();
            $competitor->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($competitor->isTransactionActive()) {
                $competitor->rollBack();
            }
            $competitor->close();
        }
        self::assertFalse($this->transaction(fn(): bool => $this->grants->replace($first, $loser)));
        self::assertFalse($this->transaction(
            fn(): bool => $this->grants->replaceWithSuccessor($first, $loser, $successor)
        ));
        self::assertSame(
            $successor->getId()->toString(),
            $this->grants->getLatestByUserId($user->getId())?->getId()->toString()
        );
    }

    /**
     * Verifies fabricated expected state and skipped revision cannot replace the authority
     */
    public function testFabricatedExpectedStateAndSkippedRevisionCannotReplaceTheAuthority(): void
    {
        $user = $this->user();
        [$first] = $this->grant($user->getId());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($first)));
        $fabricated = PersistedActivationGrant::reconstitute(
            $first->getId(),
            $first->getUserId(),
            $first->getCredentialHash(),
            $first->getExpiresAt(),
            $first->getDelivery()->invalidate(),
            null,
            null,
            $first->getRevision()
        );
        $replacement = $first->revoke($this->issuedAt->modify('+1 minute'));
        self::assertFalse($this->transaction(fn(): bool => $this->grants->replace($fabricated, $replacement)));
        self::assertFalse($this->transaction(fn(): bool => $this->grants->replace($first, $fabricated)));
        $skipped = PersistedActivationGrant::reconstitute(
            $replacement->getId(),
            $replacement->getUserId(),
            $replacement->getCredentialHash(),
            $replacement->getExpiresAt(),
            $replacement->getDelivery(),
            null,
            $replacement->getRevokedAt(),
            2
        );
        self::assertFalse($this->transaction(fn(): bool => $this->grants->replace($first, $skipped)));
        self::assertTrue(ActivationGrantRecords::same($first, $this->grants->getById($first->getId())));
    }

    /**
     * Verifies duplicate global ids and delivery ids are rejected without leaking database details
     */
    public function testDuplicateGlobalIdsAndDeliveryIdsAreRejectedWithoutLeakingDatabaseDetails(): void
    {
        $user = $this->user();
        [$first] = $this->grant($user->getId());
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($first)));
        $other = $this->user('other@example.test');
        [$fresh] = $this->grant($other->getId());
        $duplicateId = PersistedActivationGrant::reconstitute(
            $first->getId(),
            $other->getId(),
            $fresh->getCredentialHash(),
            $fresh->getExpiresAt(),
            $fresh->getDelivery(),
            null,
            null,
            0
        );
        self::assertFalse($this->transaction(fn(): bool => $this->grants->add($duplicateId)));
        $delivery = $first->getDelivery();
        $copied = \App\Adapter\Persistence\Hydration\PersistedActivationDelivery::reconstitute(
            $delivery->getId(),
            $other->getId(),
            $fresh->getDelivery()->getEmail(),
            $fresh->getDelivery()->getEncryptedMaterial(),
            $fresh->getExpiresAt(),
            $fresh->getDelivery()->getDueAt(),
            $fresh->getDelivery()->getStatus(),
            null,
            null,
            null,
            0,
            null,
            null,
            null
        );
        $duplicateDelivery = PersistedActivationGrant::reconstitute(
            $fresh->getId(),
            $other->getId(),
            $fresh->getCredentialHash(),
            $fresh->getExpiresAt(),
            $copied,
            null,
            null,
            0
        );
        self::assertFalse($this->transaction(fn(): bool => $this->grants->add($duplicateDelivery)));
        self::assertNull($this->grants->getLatestByUserId($other->getId()));
        self::assertTrue($this->transaction(fn(): bool => $this->grants->add($fresh)));
    }

    /**
     * Verifies competing successor insert and initial add are fenced
     */
    public function testCompetingSuccessorInsertAndInitialAddAreFenced(): void
    {
        $user = $this->user();
        [$first] = $this->grant($user->getId());
        [$loser] = $this->grant($user->getId());
        $this->race(
            fn(): bool => $this->grants->add($first),
            fn(PostgresActivationGrantRepository $repo): bool => $repo->add($loser)
        );
        self::assertFalse($this->transaction(fn(): bool => $this->grants->add($loser)));
        $terminal = $first->revoke($this->issuedAt->modify('+1 minute'));
        self::assertTrue($this->transaction(fn(): bool => $this->grants->replace($first, $terminal)));
        [$winner] = $this->grant($user->getId());
        $this->race(
            fn(): bool => $this->grants->addSuccessor($winner),
            fn(PostgresActivationGrantRepository $repo): bool => $repo->addSuccessor($loser)
        );
        self::assertFalse($this->transaction(fn(): bool => $this->grants->addSuccessor($loser)));
        self::assertSame(
            $winner->getId()->toString(),
            $this->grants->getLatestByUserId($user->getId())?->getId()->toString()
        );
    }

    /**
     * Verifies invalid persisted shape returns a safe conflict without poisoning caller transaction
     */
    public function testInvalidPersistedShapeReturnsASafeConflictWithoutPoisoningCallerTransaction(): void
    {
        $user = $this->user();
        [$grant] = $this->grant($user->getId());
        $invalid = PersistedActivationGrant::reconstitute(
            $grant->getId(),
            $user->getId(),
            'invalid-digest',
            $grant->getExpiresAt(),
            $grant->getDelivery(),
            null,
            null,
            0
        );
        self::assertTrue($this->transaction(function () use ($invalid, $grant): bool {
            self::assertFalse($this->grants->add($invalid));

            return $this->grants->add($grant);
        }));
        self::assertNotNull($this->grants->getById($grant->getId()));
    }

    /**
     * Verifies missing owner and transactionless mutation fail safely
     */
    public function testMissingOwnerAndTransactionlessMutationFailSafely(): void
    {
        [$grant] = $this->grant(UserId::generate());
        try {
            $this->grants->add($grant);
            self::fail('A repository write must require the caller transaction.');
        } catch (LogicException $exception) {
            self::assertSame(
                'Activation-grant persistence requires an enclosing transaction.',
                $exception->getMessage()
            );
        }
        $this->expectException(PersistenceConflict::class);
        $this->expectExceptionMessage('owner is unavailable');
        $this->transaction(fn(): bool => $this->grants->add($grant));
    }

    /**
     * Coordinates two real transactions, verifies a blocked writer, then checks its stale retry
     *
     * @phpstan-param \Closure(): bool $winner
     * @phpstan-param \Closure(PostgresActivationGrantRepository): bool $loser
     */
    private function race(\Closure $winner, \Closure $loser): void
    {
        $competing = $this->connection();
        $otherRepo = new PostgresActivationGrantRepository($competing);
        $this->connection->beginTransaction();
        $competing->beginTransaction();
        $competing->executeStatement("SET LOCAL lock_timeout = '100ms'");
        try {
            self::assertTrue($winner());
            try {
                $loser($otherRepo);
                self::fail('A competing writer must wait for the grant fence.');
            } catch (DriverException $exception) {
                self::assertSame('55P03', $exception->getSQLState());
            }
            $this->connection->commit();
            $competing->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($competing->isTransactionActive()) {
                $competing->rollBack();
            }
            $competing->close();
        }
    }

    /**
     * Creates a pending user for repository checks
     */
    private function user(string $email = 'first@example.test'): User
    {
        $user = User::invite(UserId::generate(), EmailAddress::fromString($email), $this->issuedAt);
        $this->transaction(fn() => $this->users->add($user));

        return $user;
    }

    /** @phpstan-return array{ActivationGrant, ActivationCredential} */
    private function grant(
        UserId $id,
        ?ActivationCredential $credential = null,
        ?DateTimeImmutable $issuedAt = null
    ): array {
        $credential ??= ActivationCredential::fromString(bin2hex(random_bytes(32)));
        $issuedAt ??= $this->issuedAt;

        return [ActivationGrant::issue(
            $id,
            $credential,
            $issuedAt,
            $issuedAt->modify('+1 day'),
            EmailAddress::fromString('first@example.test'),
            base64_encode(random_bytes(64))
        ), $credential];
    }

    /**
     * Runs the operation within a test transaction
     */
    private function transaction(\Closure $operation): mixed
    {
        return $this->unitOfWork->commitTransactional($operation);
    }

    /**
     * Opens a guarded PostgreSQL test connection
     */
    private function connection(): Connection
    {
        $url = getenv('TEST_DATABASE_URL');
        $host = getenv('TEST_DATABASE_ALLOWED_HOST');
        self::assertIsString($url);
        self::assertIsString($host);
        $guard = new DatabaseTargetGuard(array_map('trim', explode(',', $host)));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $connection = DriverManager::getConnection((new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ]))->parse($url));
        $guard->assertConnected($connection, $expected);

        return $connection;
    }
}
