<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\CredentialDelivery\SodiumCredentialDeliveryCipher;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\CommandHandler\DeliverUserInvitationHandler;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\DeliverUserInvitation;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\ActivationDeliveryNotRetryableException;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Exception\CredentialDeliveryTransitionException;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindCredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\DeliverPasswordReset;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Exception\PasswordResetDeliveryException;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\Event\UserInvited;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves direct package delivery against guarded PostgreSQL
 */
final class DirectCredentialDeliveryTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    private UserId $userId;
    private DateTimeImmutable $now;
    private SodiumCredentialDeliveryCipher $activationCipher;
    private SodiumCredentialDeliveryCipher $resetCipher;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $url = getenv('TEST_DATABASE_URL');
        $host = getenv('TEST_DATABASE_ALLOWED_HOST');
        self::assertIsString($url);
        self::assertIsString($host);
        $guard = new DatabaseTargetGuard(array_map('trim', explode(',', $host)));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection((new DsnParser([
            'postgres'   => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql'
        ]))->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement('ALTER TABLE audit_evidence DROP CONSTRAINT IF EXISTS delivery_test_abort');
        $this->connection->executeStatement(
            'TRUNCATE audit_evidence, activation_grants, password_reset_grants, users CASCADE'
        );
        $this->now = new DateTimeImmutable('2026-10-02T12:00:00+00:00');
        $this->userId = UserId::generate();
        $this->connection->insert('users', [
            'id'                                => $this->userId->toString(),
            'email'                             => 'delivery@example.test',
            'state'                             => 'pending_activation',
            'password_hash'                     => null,
            'authentication_version'            => 1,
            'authentication_authority_revision' => 0,
            'authorization_assignment_revision' => 0,
            'pending_email_change'              => null,
            'email_change_reservation_revision' => 0,
            'canonical_email_revision'          => 0,
            'created_at'                        => $this->now->format('Y-m-d H:i:s.uP'),
            'updated_at'                        => $this->now->format('Y-m-d H:i:s.uP')
        ]);
        $key = bin2hex(random_bytes(32));
        $this->activationCipher = new SodiumCredentialDeliveryCipher($key, 'activation');
        $this->resetCipher = new SodiumCredentialDeliveryCipher($key, 'password_reset');
        $this->container = require dirname(__DIR__, 3).'/config/services.php';
        $this->container->set(Connection::class, fn(): Connection => $this->connection);
        $this->container->set(
            InvitationDeliveryCipher::class,
            fn(): InvitationDeliveryCipher => $this->activationCipher
        );
        $this->container->set(
            PasswordResetDeliveryCipher::class,
            fn(): PasswordResetDeliveryCipher => $this->resetCipher
        );
        $this->container->set(Clock::class, fn(): Clock => new class ($this->now) implements Clock {
            /**
             * Constructs the controllable clock
             */
            public function __construct(public DateTimeImmutable $time)
            {
            }

            /**
             * @inheritDoc
             */
            public function now(): DateTimeImmutable
            {
                return $this->time;
            }
        });
    }

    /**
     * Proves claim commit, transaction-free invocation, typed outcomes and safe status for both families
     */
    public function testDirectHandlersCommitClaimsBeforeProviderAndRecordOutcomes(): void
    {
        $this->seed('activation');
        $reset = $this->seed('password_reset');
        $seen = [];
        $this->provider(function (CredentialDeliveryInvocation $invocation) use (&$seen): CredentialDeliveryOutcome {
            self::assertFalse($this->connection->isTransactionActive());
            $row = $this->row($invocation->getPurpose());
            self::assertSame('claimed', $row['delivery_status']);
            self::assertSame(1, (int) $row['delivery_attempt_count']);
            $seen[] = [$invocation->getPurpose(), $invocation->getIdempotencyId(), $invocation->getCredential()];

            if ($invocation->getPurpose() === 'activation') {
                return CredentialDeliveryOutcome::DELIVERED;
            }

            return CredentialDeliveryOutcome::RETRYABLE_FAILURE;
        });
        self::assertCount(2, $this->queries()->fetch(new FindDueCredentialDeliveries($this->now, 10)));
        $this->dispatch('activation');
        $this->dispatch('password_reset');
        self::assertCount(2, $seen);
        self::assertSame('delivered', $this->row('activation')['delivery_status']);
        self::assertNull($this->row('activation')['delivery_ciphertext']);
        self::assertSame('retry_pending', $this->row('password_reset')['delivery_status']);
        self::assertSame($reset->getDelivery()->getId()->toString(), $seen[1][1]);
        $due = $this->queries()->fetch(new FindDueCredentialDeliveries($this->now->modify('+1 minute'), 10));
        self::assertSame($seen[1][1], $due[0]->getDeliveryId()->toString());
        $status = $this->queries()->fetch(new FindCredentialDeliveryStatus('password_reset', $seen[1][1]));
        self::assertSame(CredentialDeliveryStatus::RETRY_PENDING, $status->getStatus());
        self::assertSame(CredentialDeliveryFailure::RETRYABLE_PROVIDER, $status->getLastFailure());
        self::assertStringNotContainsString($seen[1][2], var_export($status, true));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    /**
     * Proves a post-commit package event routes to its direct handler
     */
    public function testImmediateEventCoordinatesCommittedWork(): void
    {
        $grant = $this->seed('activation');
        self::assertInstanceOf(ActivationGrant::class, $grant);
        $calls = 0;
        $this->provider(function (CredentialDeliveryInvocation $invocation) use (&$calls): CredentialDeliveryOutcome {
            self::assertFalse($this->connection->isTransactionActive());
            self::assertSame('claimed', $this->row('activation')['delivery_status']);
            $calls++;

            return CredentialDeliveryOutcome::DELIVERED;
        });
        $this->container->get(EventDispatcher::class)->trigger(new UserInvited(
            $this->userId->toString(),
            $this->userId,
            $grant->getDelivery()->getId(),
            EmailAddress::fromString('delivery@example.test'),
            $this->now
        ));
        self::assertSame(1, $calls);
        self::assertSame('delivered', $this->row('activation')['delivery_status']);
    }

    /**
     * Proves terminal work cannot reach a provider again
     */
    public function testTerminalWorkCannotInvokeProvider(): void
    {
        $this->seed('activation');
        $calls = 0;
        $this->provider(function () use (&$calls): CredentialDeliveryOutcome {
            $calls++;

            return CredentialDeliveryOutcome::PERMANENT_FAILURE;
        });
        $this->dispatch('activation');
        self::assertSame('permanent_failure', $this->row('activation')['delivery_status']);
        self::assertNull($this->row('activation')['delivery_ciphertext']);
        self::assertSame([], $this->queries()->fetch(new FindDueCredentialDeliveries($this->now, 10)));
        $this->expectException(CredentialDeliveryTransitionException::class);
        try {
            $this->dispatch('activation');
        } finally {
            self::assertSame(1, $calls);
        }
    }

    /**
     * Rejects a retry before its due time without changing the committed failure
     */
    #[DataProvider('purposes')]
    public function testPrematureRetryCannotInvokeProvider(string $purpose): void
    {
        $this->seed($purpose);
        $calls = 0;
        $this->provider(function () use (&$calls): CredentialDeliveryOutcome {
            $calls++;

            return CredentialDeliveryOutcome::RETRYABLE_FAILURE;
        });
        $this->dispatch($purpose);
        $before = $this->row($purpose);
        self::assertSame('retry_pending', $before['delivery_status']);
        self::assertSame(1, (int) $before['delivery_attempt_count']);
        self::assertSame([], $this->queries()->fetch(new FindDueCredentialDeliveries($this->now, 10)));
        $this->expectException(CredentialDeliveryTransitionException::class);
        try {
            $this->dispatch($purpose);
        } finally {
            self::assertSame(1, $calls);
            self::assertSame($before, $this->row($purpose));
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        }
    }

    /**
     * Rejects duplicate delivery after a committed success without another provider call
     */
    #[DataProvider('purposes')]
    public function testDeliveredDuplicateCannotInvokeProvider(string $purpose): void
    {
        $this->seed($purpose);
        $calls = 0;
        $this->provider(function () use (&$calls): CredentialDeliveryOutcome {
            $calls++;

            return CredentialDeliveryOutcome::DELIVERED;
        });
        $this->dispatch($purpose);
        $before = $this->row($purpose);
        self::assertSame('delivered', $before['delivery_status']);
        self::assertNull($before['delivery_ciphertext']);
        $this->expectException(CredentialDeliveryTransitionException::class);
        try {
            $this->dispatch($purpose);
        } finally {
            self::assertSame(1, $calls);
            self::assertSame($before, $this->row($purpose));
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        }
    }

    /**
     * Rejects the old delivery ID after a newer grant generation is committed
     */
    #[DataProvider('purposes')]
    public function testReplacedGenerationCannotInvokeProvider(string $purpose): void
    {
        $old = $this->seed($purpose);
        $new = $this->issue($purpose);
        if ($purpose === 'activation') {
            $repo = $this->container->get(ActivationGrantRepository::class);
        } else {
            $repo = $this->container->get(PasswordResetGrantRepository::class);
        }
        $terminal = $old->revoke($this->now);
        self::assertTrue($this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn(): bool => $repo->replaceWithSuccessor($old, $terminal, $new)
        ));
        $oldId = $old->getDelivery()->getId()->toString();
        $newId = $new->getDelivery()->getId()->toString();
        $oldRow = $this->rowForDelivery($purpose, $oldId);
        $newRow = $this->rowForDelivery($purpose, $newId);
        self::assertSame('invalidated', $oldRow['delivery_status']);
        self::assertNull($oldRow['delivery_ciphertext']);
        self::assertSame('pending', $newRow['delivery_status']);
        self::assertSame(0, (int) $newRow['delivery_attempt_count']);
        $auditCount = (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence');
        $calls = 0;
        $this->provider(function () use (&$calls): CredentialDeliveryOutcome {
            $calls++;

            return CredentialDeliveryOutcome::DELIVERED;
        });
        $exception = match ($purpose) {
            'activation' => ActivationDeliveryNotRetryableException::class,
            default => PasswordResetDeliveryException::class
        };
        $this->expectException($exception);
        try {
            $this->dispatchGrant($old);
        } finally {
            self::assertSame(0, $calls);
            self::assertSame($oldRow, $this->rowForDelivery($purpose, $oldId));
            self::assertSame($newRow, $this->rowForDelivery($purpose, $newId));
            self::assertSame($auditCount, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        }
    }

    /**
     * Supplies the two directly registered package delivery families
     *
     * @return iterable<string, array{string}>
     */
    public static function purposes(): iterable
    {
        yield 'invitation' => ['activation'];
        yield 'password reset' => ['password_reset'];
    }

    /**
     * Proves absent work cannot reach a provider
     */
    public function testAbsentWorkCannotInvokeProvider(): void
    {
        $calls = 0;
        $this->provider(function () use (&$calls): CredentialDeliveryOutcome {
            $calls++;

            return CredentialDeliveryOutcome::DELIVERED;
        });
        $this->expectException(ActivationDeliveryNotRetryableException::class);
        try {
            $this->container->get(CommandBus::class)->execute(new DeliverUserInvitation(
                $this->userId->toString(),
                $this->userId,
                ActivationDeliveryId::generate()
            ));
        } finally {
            self::assertSame(0, $calls);
        }
    }

    /**
     * Proves an originating rollback leaves no invocable delivery
     */
    public function testRolledBackGrantCannotInvokeProvider(): void
    {
        $raw = bin2hex(random_bytes(32));
        $grant = ActivationGrant::issue(
            $this->userId,
            ActivationCredential::fromString($raw),
            $this->now,
            $this->now->modify('+1 hour'),
            EmailAddress::fromString('delivery@example.test'),
            $this->activationCipher->encrypt($raw)
        );
        $this->connection->beginTransaction();
        self::assertTrue($this->container->get(ActivationGrantRepository::class)->add($grant));
        $this->connection->rollBack();
        $calls = 0;
        $this->provider(function () use (&$calls): CredentialDeliveryOutcome {
            $calls++;

            return CredentialDeliveryOutcome::DELIVERED;
        });
        $this->expectException(ActivationDeliveryNotRetryableException::class);
        try {
            $this->container->get(CommandBus::class)->execute(new DeliverUserInvitation(
                $this->userId->toString(),
                $this->userId,
                $grant->getDelivery()->getId()
            ));
        } finally {
            self::assertSame(0, $calls);
            self::assertSame([], $this->queries()->fetch(new FindDueCredentialDeliveries($this->now, 10)));
        }
    }

    /**
     * Proves a superseded claim cannot commit its provider outcome
     */
    public function testStaleOutcomeCannotOverwriteRevocation(): void
    {
        $this->seed('activation');
        $this->provider(function (): CredentialDeliveryOutcome {
            self::assertFalse($this->connection->isTransactionActive());
            $repo = $this->container->get(ActivationGrantRepository::class);
            $claimed = $repo->getLatestByUserId($this->userId);
            $this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
                fn(): bool => $repo->replace($claimed, $claimed->revoke($this->now->modify('+1 minute')))
            );

            return CredentialDeliveryOutcome::DELIVERED;
        });
        $this->expectException(ActivationDeliveryNotRetryableException::class);
        try {
            $this->dispatch('activation');
        } finally {
            self::assertSame('invalidated', $this->row('activation')['delivery_status']);
            self::assertNull($this->row('activation')['delivery_ciphertext']);
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        }
    }

    /**
     * Proves unexpected provider failure persists only the package safe classification
     */
    public function testUnexpectedProviderExceptionRemainsRetryableWithoutErrorDisclosure(): void
    {
        $this->seed('password_reset');
        $sensitive = bin2hex(random_bytes(32));
        $this->provider(static function () use ($sensitive): CredentialDeliveryOutcome {
            throw new \RuntimeException($sensitive);
        });
        $this->dispatch('password_reset');
        $row = $this->row('password_reset');
        self::assertSame('retry_pending', $row['delivery_status']);
        self::assertSame('unexpected_provider', $row['delivery_last_failure']);
        self::assertStringNotContainsString($sensitive, json_encode($row, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($sensitive, json_encode(
            $this->connection->fetchAllAssociative('SELECT * FROM audit_evidence'),
            JSON_THROW_ON_ERROR
        ));
    }

    /**
     * Proves a claim that expires during invocation cannot confirm a stale outcome
     */
    public function testExpiredClaimRemainsDiscoverableWithStableIdentity(): void
    {
        $grant = $this->seed('activation');
        self::assertInstanceOf(ActivationGrant::class, $grant);
        $clock = $this->container->get(Clock::class);
        $seen = [];
        $probe = function (CredentialDeliveryInvocation $invocation) use ($clock, &$seen): CredentialDeliveryOutcome {
            $seen[] = $invocation->getIdempotencyId();
            $clock->time = $this->now->modify('+5 minutes');

            return CredentialDeliveryOutcome::DELIVERED;
        };
        $this->provider($probe);
        $this->expectException(CredentialDeliveryTransitionException::class);
        try {
            $this->dispatch('activation');
        } finally {
            self::assertSame([$grant->getDelivery()->getId()->toString()], $seen);
            self::assertSame('claimed', $this->row('activation')['delivery_status']);
            $due = $this->queries()->fetch(new FindDueCredentialDeliveries($clock->time, 10));
            self::assertSame($seen[0], $due[0]->getDeliveryId()->toString());
        }
    }

    /**
     * Proves provider acceptance followed by a failed outcome commit retains the generation identity
     */
    public function testProviderSuccessThenOutcomeRollbackReusesIdempotencyIdentity(): void
    {
        $grant = $this->seed('activation');
        $ids = [];
        $this->provider(function (CredentialDeliveryInvocation $invocation) use (&$ids): CredentialDeliveryOutcome {
            $ids[] = $invocation->getIdempotencyId();
            if (count($ids) === 1) {
                // Reject the outcome audit write without changing claim persistence.
                $this->connection->executeStatement(
                    'ALTER TABLE audit_evidence ADD CONSTRAINT delivery_test_abort CHECK (false) NOT VALID'
                );
            }

            return CredentialDeliveryOutcome::DELIVERED;
        });
        try {
            $this->dispatch('activation');
            self::fail('Outcome transaction must roll back.');
        } catch (DriverException) {
            self::assertSame('claimed', $this->row('activation')['delivery_status']);
        } finally {
            $this->connection->executeStatement('ALTER TABLE audit_evidence DROP CONSTRAINT delivery_test_abort');
        }
        // Doctrine closes its manager on rollback; a recovered worker uses a new one.
        $clock = $this->container->get(Clock::class);
        $clock->time = $this->now->modify('+5 minutes');
        $config = ORMSetup::createAttributeMetadataConfiguration([], true);
        $config->enableNativeLazyObjects(true);
        $this->container->set(TransactionalUnitOfWork::class, fn(): TransactionalUnitOfWork =>
            new DoctrineTransactionalUnitOfWork(new EntityManager($this->connection, $config)));
        $this->container->set(DeliverUserInvitationHandler::class, fn(): DeliverUserInvitationHandler =>
            new DeliverUserInvitationHandler(
                $this->container->get(ActivationGrantRepository::class),
                $this->container->get(AuditEvidenceRepository::class),
                $this->container->get(TransactionalUnitOfWork::class),
                $this->activationCipher,
                $this->container->get(CredentialDeliveryProvider::class),
                $clock,
                $this->container->get(EventDispatcher::class)
            ));
        $this->dispatch('activation');
        self::assertSame(array_fill(0, 2, $grant->getDelivery()->getId()->toString()), $ids);
        self::assertSame('delivered', $this->row('activation')['delivery_status']);
    }

    /**
     * Proves a competing live claim is fenced before provider invocation
     */
    public function testCompetingClaimCannotInvokeProvider(): void
    {
        $this->seed('activation');
        $this->connection->beginTransaction();
        $other = DriverManager::getConnection($this->connection->getParams());
        try {
            $repo = $this->container->get(ActivationGrantRepository::class);
            $grant = $repo->getLatestByUserId($this->userId);
            $claim = fn() => $grant->claimDelivery(
                CredentialDeliveryClaimToken::generate(),
                $this->now,
                $this->now->modify('+5 minutes')
            );
            self::assertTrue($repo->replace($grant, $claim()));
            $other->beginTransaction();
            $other->executeStatement("SET LOCAL lock_timeout = '100ms'");
            try {
                (new \App\Adapter\Persistence\Repository\PostgresActivationGrantRepository($other))
                    ->replace($grant, $claim());
                self::fail('Competing claim must not pass the live lock.');
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
        $calls = 0;
        $this->provider(function () use (&$calls): CredentialDeliveryOutcome {
            $calls++;

            return CredentialDeliveryOutcome::DELIVERED;
        });
        $this->expectException(CredentialDeliveryTransitionException::class);
        try {
            $this->dispatch('activation');
        } finally {
            self::assertSame(0, $calls);
        }
    }

    /**
     * Configures a synchronous provider probe
     *
     * @param callable(CredentialDeliveryInvocation): CredentialDeliveryOutcome $deliver
     */
    private function provider(callable $deliver): void
    {
        $this->container->set(CredentialDeliveryProvider::class, static function () use (
            $deliver
        ): CredentialDeliveryProvider {
            return new class (\Closure::fromCallable($deliver)) implements CredentialDeliveryProvider {
                /**
                 * Constructs the provider probe
                 *
                 * @param \Closure $deliver Invocation probe
                 */
                public function __construct(private \Closure $deliver)
                {
                }

                /**
                 * @inheritDoc
                 */
                public function deliver(CredentialDeliveryInvocation $invocation): CredentialDeliveryOutcome
                {
                    return ($this->deliver)($invocation);
                }
            };
        });
    }

    /**
     * Commits one package grant with encrypted recoverable material
     */
    private function seed(string $purpose): ActivationGrant|PasswordResetGrant
    {
        $grant = $this->issue($purpose);
        $repository = match ($purpose) {
            'activation' => ActivationGrantRepository::class,
            default => PasswordResetGrantRepository::class
        };
        $this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn(): bool => $this->container->get($repository)->add($grant)
        );

        return $grant;
    }

    /**
     * Issues one new package generation with encrypted recoverable material
     */
    private function issue(string $purpose): ActivationGrant|PasswordResetGrant
    {
        $raw = bin2hex(random_bytes(32));
        if ($purpose === 'activation') {
            $grant = ActivationGrant::issue(
                $this->userId,
                ActivationCredential::fromString($raw),
                $this->now,
                $this->now->modify('+1 hour'),
                EmailAddress::fromString('delivery@example.test'),
                $this->activationCipher->encrypt($raw)
            );
        } else {
            $grant = PasswordResetGrant::issue(
                $this->userId,
                PasswordResetCredential::fromString($raw),
                $this->now,
                $this->now->modify('+1 hour'),
                EmailAddress::fromString('delivery@example.test'),
                $this->resetCipher->encrypt($raw)
            );
        }

        return $grant;
    }

    /**
     * Invokes the exact package command through the registered bus
     */
    private function dispatch(string $purpose): void
    {
        $repository = match ($purpose) {
            'activation' => ActivationGrantRepository::class,
            default => PasswordResetGrantRepository::class
        };
        $grant = $this->container->get($repository)->getLatestByUserId($this->userId);
        self::assertNotNull($grant);
        $this->dispatchGrant($grant);
    }

    /**
     * Invokes the package command for an exact generation, even after replacement
     */
    private function dispatchGrant(ActivationGrant|PasswordResetGrant $grant): void
    {
        if ($grant instanceof ActivationGrant) {
            $command = new DeliverUserInvitation(
                $this->userId->toString(),
                $this->userId,
                $grant->getDelivery()->getId()
            );
        } else {
            $command = new DeliverPasswordReset('anonymous', $this->userId, $grant->getDelivery()->getId());
        }
        $this->container->get(CommandBus::class)->execute($command);
    }

    /**
     * Returns the registered package query bus
     */
    private function queries(): QueryBus
    {
        return $this->container->get(QueryBus::class);
    }

    /**
     * Reads persisted state at the PostgreSQL boundary
     *
     * @return array<string, mixed>
     */
    private function row(string $purpose): array
    {
        $table = $purpose === 'activation' ? 'activation_grants' : 'password_reset_grants';
        $row = $this->connection->fetchAssociative('SELECT * FROM '.$table.' WHERE user_id = ?', [
            $this->userId->toString()
        ]);
        self::assertIsArray($row);

        return $row;
    }

    /**
     * Reads one exact persisted generation after a replacement
     *
     * @return array<string, mixed>
     */
    private function rowForDelivery(string $purpose, string $deliveryId): array
    {
        $table = $purpose === 'activation' ? 'activation_grants' : 'password_reset_grants';
        $row = $this->connection->fetchAssociative('SELECT * FROM '.$table.' WHERE delivery_id = ?', [$deliveryId]);
        self::assertIsArray($row);

        return $row;
    }
}
