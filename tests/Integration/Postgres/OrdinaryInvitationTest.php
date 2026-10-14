<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Console\InviteUserCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\CredentialDelivery\SodiumCredentialDeliveryCipher;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\Repository\PostgresRoleRepository;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\EventSubscriber\InvitationDeliverySubscriber;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\ActivationCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\DeliverUserInvitation;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindCredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPermissionPlanItem;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyChangeAction;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyPlan;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Event\UserInvited;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\AllEvents;
use Fight\Common\Domain\Messaging\Event\CommandFailedEvent;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Tests\Support\AccessClock;

/**
 * Proves one coherent ordinary invitation across package behavior, PostgreSQL and delivery
 */
final class OrdinaryInvitationTest extends TestCase
{
    private const string RAW_MARKER = 'invitation-raw-marker-not-for-output';
    private Connection $connection;
    private Container $container;
    private AccessClock $clock;
    private string $operator;
    private string $output = '';
    private string $error = '';
    /** @var list<array<string, mixed>> */
    private array $events = [];
    private int $providerCalls = 0;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection((new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ]))->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement('TRUNCATE users, roles, permissions, audit_evidence CASCADE');
        $this->clock = new AccessClock(new DateTimeImmutable('2026-10-12T12:00:00+00:00'));
        $this->operator = UserId::generate()->toString();
        $this->container = $this->newContainer();
        $this->container->get(SynchronousCommandBus::class)->execute(
            new ReconcileManagedPolicy(InstallationManagedPolicy::create())
        );
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(AllEvents::class),
            function (EventMessage $message): void {
                $event = $message->payload();
                // Only safe success facts are operator-facing; private failure payloads are never presented.
                if (!$event instanceof CommandFailedEvent) {
                    self::assertFalse($this->connection->isTransactionActive());
                    $this->events[] = $event->toArray();
                }
            }
        );
        $this->provider(CredentialDeliveryOutcome::RETRYABLE_FAILURE);
    }

    /**
     * Releases the guarded fixture and removes only its own failure injection
     */
    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }
        foreach (['users', 'activation_grants', 'user_role_assignments', 'audit_evidence'] as $table) {
            $this->connection->executeStatement(
                'ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS invitation_test_abort'
            );
        }
        $this->connection->executeStatement('DROP TRIGGER IF EXISTS invitation_test_commit_abort ON users');
        $this->connection->executeStatement('DROP FUNCTION IF EXISTS invitation_test_commit_abort()');
        $this->connection->close();
    }

    /**
     * Creates exactly one pending ordinary identity and a seven-day recoverable package generation
     */
    public function testInvitationAndDeliveryRecovery(): void
    {
        self::assertSame(0, $this->runConsole('New.User@EXAMPLE.test'));
        $result = json_decode($this->output, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('new.user@example.test', $result['email']);
        self::assertSame('invited', $result['status']);
        $user = $this->connection->fetchAssociative('SELECT * FROM users');
        self::assertIsArray($user);
        self::assertSame($result['user_id'], $user['id']);
        self::assertSame('pending_activation', $user['state']);
        self::assertNull($user['password_hash']);
        self::assertSame([InstallationManagedPolicy::USER_ROLE_ID], $this->connection->fetchFirstColumn(
            'SELECT role_id FROM user_role_assignments WHERE user_id = ?',
            [$result['user_id']]
        ));
        self::assertSame(
            ['DELETE_OWN_SESSIONS', 'READ_OWN_SESSIONS', 'VIEW_DASHBOARD'],
            $this->connection->fetchFirstColumn(
                <<<'SQL'
SELECT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id
JOIN user_role_assignments ur ON ur.role_id = rp.role_id ORDER BY p.name
SQL
            )
        );
        $grant = $this->grant();
        self::assertEquals($this->clock->now()->modify('+7 days'), new DateTimeImmutable($grant['expires_at']));
        self::assertSame('retry_pending', $grant['delivery_status']);
        self::assertSame(1, (int) $grant['delivery_attempt_count']);
        self::assertSame(1, $this->providerCalls);
        $audit = $this->connection->fetchAllAssociative(
            'SELECT actor_id, action, subject_id, context FROM audit_evidence ORDER BY action'
        );
        self::assertSame([
            'user.console_role_assigned', 'user.invitation_delivery.failed', 'user.invited'
        ], array_column($audit, 'action'));
        foreach ($audit as $row) {
            self::assertSame('console:'.$this->operator, $row['actor_id']);
            self::assertSame($result['user_id'], $row['subject_id']);
            self::assertSame('{}', $row['context']);
        }
        self::assertCount(2, $this->events);
        self::assertSame('console:'.$this->operator, $this->events[0]['actor_id']);
        self::assertSame($this->operator, $this->events[1]['actor_id']);
        self::assertNotSame($result['user_id'], $this->events[1]['actor_id']);
        $this->assertRedacted($grant, $audit);

        // A fresh worker discovers package-owned work without any consumer intent registration or original event.
        $this->clock->time = $this->clock->now()->modify('+1 minute');
        $worker = $this->newContainer();
        $worker->set(CredentialDeliveryProvider::class, fn(): CredentialDeliveryProvider => $this->deliveryProvider(
            CredentialDeliveryOutcome::DELIVERED
        ));
        $due = $worker->get(QueryBus::class)->fetch(new FindDueCredentialDeliveries($this->clock->now(), 10));
        self::assertCount(1, $due);
        self::assertSame($grant['delivery_id'], $due[0]->getDeliveryId()->toString());
        $worker->get(SynchronousCommandBus::class)->execute(new DeliverUserInvitation(
            'credential-recovery',
            UserId::fromString($result['user_id']),
            $due[0]->getDeliveryId()
        ));
        self::assertSame('delivered', $this->grant()['delivery_status']);
        self::assertNull($this->grant()['delivery_ciphertext']);
        self::assertSame(2, $this->providerCalls);
    }

    /**
     * Refuses a canonical conflict without replacing grants or regenerating credentials
     */
    public function testDuplicateAndRepeat(): void
    {
        self::assertSame(0, $this->runConsole('user@example.test'));
        $before = $this->connection->fetchAllAssociative('SELECT * FROM activation_grants');
        $auditCount = $this->connection->fetchOne('SELECT count(*) FROM audit_evidence');
        self::assertSame(1, $this->runConsole('USER@EXAMPLE.TEST'));
        self::assertSame('', $this->output);
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $this->error);
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM activation_grants'));
        self::assertSame($auditCount, $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame(1, $this->providerCalls);
    }

    /**
     * Refuses absent, drifted and stale managed authority before creating any invitation
     */
    #[DataProvider('policyDrift')]
    public function testPolicyDrift(string $sql): void
    {
        $this->connection->executeStatement($sql, [InstallationManagedPolicy::USER_ROLE_ID]);
        self::assertSame(1, $this->runConsole());
        $this->assertNoInvitation();
    }

    /**
     * Supplies changes that could otherwise invite with missing or excessive authority
     *
     * @return iterable<array{string}>
     */
    public static function policyDrift(): iterable
    {
        yield ['DELETE FROM roles WHERE id = ?'];
        yield ["UPDATE roles SET name = 'ROLE_DRIFTED' WHERE id = ?"];
        yield ['DELETE FROM role_permissions WHERE role_id = ?'];
        yield ['UPDATE roles SET managed = FALSE WHERE id = ?'];
    }

    /**
     * Rolls back every originating table when any required step fails
     */
    #[DataProvider('originFailures')]
    public function testOriginFailure(string $table, string $check): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE '.$table.' ADD CONSTRAINT invitation_test_abort CHECK ('.$check.')'
        );
        self::assertSame(1, $this->runConsole());
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $this->error);
        self::assertSame('', $this->output);
        $this->assertNoInvitation();
        self::assertSame([], $this->events);
    }

    /**
     * Supplies failures after successive originating writes
     *
     * @return iterable<array{string, string}>
     */
    public static function originFailures(): iterable
    {
        yield ['users', 'FALSE'];
        yield ['activation_grants', 'FALSE'];
        yield ['user_role_assignments', 'FALSE'];
        yield ['audit_evidence', "action <> 'user.invited'"];
        yield ['audit_evidence', "action <> 'user.console_role_assigned'"];
    }

    /**
     * Discards staged events and all originating work when PostgreSQL refuses commit
     */
    public function testOriginCommitFailure(): void
    {
        $this->connection->executeStatement(<<<'SQL'
CREATE FUNCTION invitation_test_commit_abort() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'SQL private-marker credential=https://private/marker'; END $$
SQL);
        $this->connection->executeStatement(<<<'SQL'
CREATE CONSTRAINT TRIGGER invitation_test_commit_abort AFTER INSERT ON users
DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION invitation_test_commit_abort()
SQL);
        self::assertSame(1, $this->runConsole());
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $this->error);
        $this->assertNoInvitation();
        self::assertSame([], $this->events);
    }

    /**
     * Preserves discoverable work on lost post-commit dispatch rather than reporting success
     */
    public function testPostCommitEventFailure(): void
    {
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(UserInvited::class),
            static function (): never {
                throw new RuntimeException('private-event-marker https://private/credential');
            },
            100
        );
        self::assertSame(1, $this->runConsole());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $this->error);
        self::assertSame('', $this->output);
        // Dispatcher attempts other subscribers even if one fails; durable state remains authoritative.
        self::assertContains($this->grant()['delivery_status'], ['pending', 'retry_pending']);
    }

    /**
     * Keeps committed originating work recoverable when claim or outcome writes fail
     */
    #[DataProvider('deliveryFailures')]
    public function testDeliveryWriteFailure(string $check, string $status, int $calls): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE activation_grants ADD CONSTRAINT invitation_test_abort CHECK ('.$check.')'
        );
        self::assertSame(1, $this->runConsole());
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $this->error);
        self::assertSame('', $this->output);
        self::assertSame($status, $this->grant()['delivery_status']);
        self::assertSame($calls, $this->providerCalls);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        $this->connection->executeStatement('ALTER TABLE activation_grants DROP CONSTRAINT invitation_test_abort');
        $this->clock->time = $this->clock->now()->modify('+6 minutes');
        $worker = $this->newContainer();
        $worker->set(CredentialDeliveryProvider::class, fn(): CredentialDeliveryProvider => $this->deliveryProvider(
            CredentialDeliveryOutcome::DELIVERED
        ));
        $due = $worker->get(QueryBus::class)->fetch(new FindDueCredentialDeliveries($this->clock->now(), 10));
        self::assertCount(1, $due);
        $worker->get(SynchronousCommandBus::class)->execute(new DeliverUserInvitation(
            'credential-recovery',
            $due[0]->getUserId(),
            $due[0]->getDeliveryId()
        ));
        self::assertSame('delivered', $this->grant()['delivery_status']);
    }

    /**
     * Supplies claim and outcome transaction failures
     *
     * @return iterable<array{string, string, int}>
     */
    public static function deliveryFailures(): iterable
    {
        yield ["delivery_status <> 'claimed'", 'pending', 0];
        yield ["delivery_status <> 'retry_pending'", 'claimed', 1];
    }

    /**
     * Classifies provider exceptions without leaking marker material or losing retry work
     */
    public function testProviderExceptionIsPersistedSafely(): void
    {
        $this->provider(CredentialDeliveryOutcome::RETRYABLE_FAILURE, true);
        self::assertSame(0, $this->runConsole());
        self::assertSame('retry_pending', $this->grant()['delivery_status']);
        self::assertSame('unexpected_provider', $this->grant()['delivery_last_failure']);
        $this->assertRedacted($this->grant(), $this->connection->fetchAllAssociative('SELECT * FROM audit_evidence'));
    }

    /**
     * Rolls back identity creation when credential preparation cannot complete
     */
    #[DataProvider('preparationFailures')]
    public function testPreparationFailure(bool $generation): void
    {
        $capability = $generation ? ActivationCredentialGenerator::class : InvitationDeliveryCipher::class;
        $failure = $this->createStub($capability);
        $failure->method($generation ? 'generate' : 'encrypt')->willThrowException(
            new RuntimeException('private-material-marker ciphertext=https://private/credential')
        );
        $this->container->set($capability, static fn() => $failure);
        self::assertSame(1, $this->runConsole());
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $this->error);
        $this->assertNoInvitation();
        self::assertSame([], $this->events);
    }

    /**
     * Supplies credential generation and encryption failures
     *
     * @return iterable<array{bool}>
     */
    public static function preparationFailures(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * Recovers an invitation when the immediate subscriber is absent after commit
     */
    public function testLostImmediateDelivery(): void
    {
        $this->container->get(EventDispatcher::class)->unregister(
            $this->container->get(InvitationDeliverySubscriber::class)
        );
        self::assertSame(0, $this->runConsole());
        self::assertSame('pending', $this->grant()['delivery_status']);
        self::assertSame(0, $this->providerCalls);
        $worker = $this->newContainer();
        $worker->set(CredentialDeliveryProvider::class, fn(): CredentialDeliveryProvider =>
            $this->deliveryProvider(CredentialDeliveryOutcome::DELIVERED));
        $due = $worker->get(QueryBus::class)->fetch(new FindDueCredentialDeliveries($this->clock->now(), 10));
        self::assertCount(1, $due);
        $worker->get(SynchronousCommandBus::class)->execute(new DeliverUserInvitation(
            'credential-recovery',
            $due[0]->getUserId(),
            $due[0]->getDeliveryId()
        ));
        self::assertSame('delivered', $this->grant()['delivery_status']);
    }

    /**
     * Refuses a stale authoritative role reference rather than leaving a pending unassigned identity
     */
    public function testStaleRoleReference(): void
    {
        $real = $this->container->get(QueryBus::class);
        $probe = $this->createMock(QueryBus::class);
        $probe->method('fetch')->willReturnCallback(function ($query) use ($real) {
            $plan = $real->fetch($query);
            if (!$query instanceof PreviewManagedPolicy) {
                return $plan;
            }
            $this->connection->delete('roles', ['id' => InstallationManagedPolicy::USER_ROLE_ID]);

            return $plan;
        });
        $this->container->set(QueryBus::class, static fn(): QueryBus => $probe);
        self::assertSame(1, $this->runConsole());
        $this->assertNoInvitation();
        self::assertSame([], $this->events);
        self::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM roles WHERE id = ?',
            [InstallationManagedPolicy::USER_ROLE_ID]
        ));
    }

    /**
     * Holds the shared authoritative role fence from policy validation through originating commit
     */
    public function testConcurrentRoleRemovalIsFenced(): void
    {
        $real = $this->container->get(QueryBus::class);
        $role = $this->container->get(RoleRepository::class)->getById(
            RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID)
        );
        self::assertNotNull($role);
        $probe = $this->createMock(QueryBus::class);
        $probe->method('fetch')->willReturnCallback(function ($query) use ($real, $role) {
            if (!$query instanceof PreviewManagedPolicy) {
                return $real->fetch($query);
            }
            $other = DriverManager::getConnection($this->connection->getParams());
            try {
                $other->beginTransaction();
                $other->executeStatement("SET LOCAL lock_timeout = '100ms'");
                $repository = new PostgresRoleRepository($other, new AuthorizationReferenceFences($other));
                try {
                    $repository->remove($role);
                    self::fail('Concurrent removal must wait for the invitation commit.');
                } catch (DriverException $failure) {
                    self::assertSame('55P03', $failure->getSQLState());
                }
            } finally {
                $other->rollBack();
                $other->close();
            }

            return $real->fetch($query);
        });
        $this->container->set(QueryBus::class, static fn(): QueryBus => $probe);
        self::assertSame(0, $this->runConsole());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
    }

    /**
     * Honors throwing and silent filter vetoes without committing partial ordinary invitations
     */
    #[DataProvider('filterVetoes')]
    public function testFilterVeto(string $commandClass, bool $throw): void
    {
        $filter = $this->createStub(CommandFilter::class);
        $filter->method('process')->willReturnCallback(static function (
            CommandMessage $message,
            callable $next
        ) use (
            $commandClass,
            $throw
        ): void {
            if ($message->payload() instanceof $commandClass) {
                if ($throw) {
                    throw new RuntimeException('private-filter-marker SQL password=secret');
                }

                return;
            }
            $next($message);
        });
        $this->container->get('messaging.command.bus')->addFilter($filter);
        self::assertSame(1, $this->runConsole());
        self::assertSame('', $this->output);
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $this->error);
        $this->assertNoInvitation();
        self::assertSame([], $this->events);
        self::assertFalse($this->connection->isTransactionActive());
    }

    /**
     * Supplies outer and nested command vetoes
     *
     * @return iterable<array{class-string, bool}>
     */
    public static function filterVetoes(): iterable
    {
        foreach ([InviteOrdinaryUser::class, InvitePendingUser::class, AssignRoleToUser::class] as $command) {
            yield [$command, true];
            yield [$command, false];
        }
    }

    /**
     * Rolls back filter-transformed elevated authority instead of silently inviting a Super Admin
     */
    public function testFilterCannotTransformOrdinaryAuthority(): void
    {
        $filter = $this->createStub(CommandFilter::class);
        $filter->method('process')->willReturnCallback(static function (
            CommandMessage $message,
            callable $next
        ): void {
            $command = $message->payload();
            if ($command instanceof AssignRoleToUser) {
                $message = CommandMessage::create(new AssignRoleToUser(
                    $command->getActorId(),
                    $command->getTargetUserId(),
                    RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID)
                ));
            }
            $next($message);
        });
        $this->container->get('messaging.command.bus')->addFilter($filter);
        self::assertSame(1, $this->runConsole());
        $this->assertNoInvitation();
        self::assertSame([], $this->events);
    }

    /**
     * Uses transformed query results from the configured query pipeline to refuse authority drift
     */
    public function testQueryFilterCanRefusePolicy(): void
    {
        $filter = $this->createStub(QueryFilter::class);
        $filter->method('process')->willReturnCallback(static function ($message, callable $next) {
            $result = $next($message);
            if (!$message->payload() instanceof PreviewManagedPolicy) {
                return $result;
            }

            self::assertInstanceOf(ManagedPolicyPlan::class, $result);

            return new ManagedPolicyPlan(
                array_map(
                    static fn($item) => new ManagedPermissionPlanItem(
                        $item->getDefinition(),
                        ManagedPolicyChangeAction::RECONCILE
                    ),
                    $result->getPermissions()
                ),
                $result->getRoles()
            );
        });
        $this->container->get('messaging.query.bus')->addFilter($filter);
        self::assertSame(1, $this->runConsole());
        $this->assertNoInvitation();
    }

    /**
     * Reuses shared commands and transaction infrastructure without retaining prior success events
     */
    public function testRepeatedSharedInvocations(): void
    {
        $this->container->get(EventDispatcher::class)->unregister(
            $this->container->get(InvitationDeliverySubscriber::class)
        );
        self::assertSame(0, $this->runConsole('first@example.test'));
        self::assertSame(0, $this->runConsole('second@example.test'));
        self::assertCount(4, $this->events);
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
        self::assertSame(4, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(0, $this->providerCalls);
    }

    /**
     * Fails closed after a rollback and recovers only through a fresh manager without replaying staged effects
     */
    public function testFailedSharedContainerRequiresFreshInvocation(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE user_role_assignments ADD CONSTRAINT invitation_test_abort CHECK (FALSE)'
        );
        self::assertSame(1, $this->runConsole('failed@example.test'));
        $this->assertNoInvitation();
        self::assertSame([], $this->events);
        self::assertFalse($this->connection->isTransactionActive());
        $this->connection->executeStatement('ALTER TABLE user_role_assignments DROP CONSTRAINT invitation_test_abort');
        self::assertSame(1, $this->runConsole('after.failure@example.test'));
        $this->assertNoInvitation();
        self::assertSame([], $this->events);
        $this->container = $this->newContainer();
        $this->provider(CredentialDeliveryOutcome::RETRYABLE_FAILURE);
        self::assertSame(0, $this->runConsole('fresh@example.test'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
        self::assertSame(1, $this->providerCalls);
        self::assertFalse($this->connection->isTransactionActive());
    }

    /**
     * Creates a fresh manager and package composition on the same guarded fixture connection
     */
    private function newContainer(): Container
    {
        $container = require dirname(__DIR__, 3).'/config/services.php';
        $configure = require dirname(__DIR__, 3).'/config/application/console_invitation.php';
        $configure($container);
        $container->set(Connection::class, fn(): Connection => $this->connection);
        $container->set(Clock::class, fn(): Clock => $this->clock);
        $container->set(InvitationDeliveryCipher::class, static fn(): InvitationDeliveryCipher =>
            new SodiumCredentialDeliveryCipher(bin2hex(random_bytes(32)), 'activation'));
        // Keep the same external key/cipher across worker invocations, as a real installation must.
        if (isset($this->container)) {
            $cipher = $this->container->get(InvitationDeliveryCipher::class);
            $container->set(InvitationDeliveryCipher::class, static fn(): InvitationDeliveryCipher => $cipher);
        }
        $generator = $this->createStub(ActivationCredentialGenerator::class);
        $generator->method('generate')->willReturn(ActivationCredential::fromString(self::RAW_MARKER));
        $container->set(ActivationCredentialGenerator::class, static fn(): ActivationCredentialGenerator => $generator);

        return $container;
    }

    /**
     * Configures a provider that can observe only committed identities, roles and claims
     */
    private function provider(CredentialDeliveryOutcome $outcome, bool $throw = false): void
    {
        $this->container->set(CredentialDeliveryProvider::class, fn(): CredentialDeliveryProvider =>
            $this->deliveryProvider($outcome, $throw));
    }

    /**
     * Constructs the real-invocation observer
     */
    private function deliveryProvider(
        CredentialDeliveryOutcome $outcome,
        bool $throw = false
    ): CredentialDeliveryProvider {
        $observer = function (CredentialDeliveryInvocation $invocation) use (
            $outcome,
            $throw
        ): CredentialDeliveryOutcome {
            self::assertFalse($this->connection->isTransactionActive());
            self::assertSame('claimed', $this->grant()['delivery_status']);
            self::assertSame($this->grant()['delivery_id'], $invocation->getIdempotencyId());
            self::assertSame(self::RAW_MARKER, $invocation->getCredential());
            self::assertSame(
                1,
                (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments')
            );
            self::assertSame(1, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM audit_evidence WHERE action = 'user.console_role_assigned'"
            ));
            $this->providerCalls++;
            if ($throw) {
                throw new RuntimeException(
                    'Authorization: Bearer private-provider-marker https://private/credential'
                );
            }

            return $outcome;
        };

        return new class ($observer) implements CredentialDeliveryProvider {
            /**
             * Constructs the observing provider
             *
             * @phpstan-param callable(CredentialDeliveryInvocation): CredentialDeliveryOutcome $observer
             */
            public function __construct(private readonly mixed $observer)
            {
            }

            /**
             * @inheritDoc
             */
            public function deliver(CredentialDeliveryInvocation $invocation): CredentialDeliveryOutcome
            {
                return ($this->observer)($invocation);
            }
        };
    }

    /**
     * Runs the shared Symfony application through real parsing and separate output streams
     */
    private function runConsole(string $email = 'user@example.test'): int
    {
        $output = new class extends ConsoleOutput {
            public string $text = '';

            /**
             * @inheritDoc
             */
            protected function doWrite(string $message, bool $newline): void
            {
                $this->text .= $message.($newline ? "\n" : '');
            }
        };
        $error = new BufferedOutput();
        $output->setErrorOutput($error);
        $exit = $this->container->get(SafeConsoleApplication::class)->run(new ArgvInput([
            'console', 'user:invite', '--internal', '--operator='.$this->operator, '--email='.$email
        ]), $output);
        $this->output = $output->text;
        $this->error = $error->fetch();

        return $exit;
    }

    /**
     * Reads private persisted state for assertions without copying it to evidence
     *
     * @return array<string, mixed>
     */
    private function grant(): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM activation_grants');
        self::assertIsArray($row);

        return $row;
    }

    /**
     * Confirms every originating effect and provider invocation was rolled back or prevented
     */
    private function assertNoInvitation(): void
    {
        $tables = ['users', 'user_email_claims', 'activation_grants', 'user_role_assignments', 'audit_evidence'];
        foreach ($tables as $table) {
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM '.$table));
        }
        self::assertSame(0, $this->providerCalls);
    }

    /**
     * Inspects output, audit, safe status and operator-facing event facts for sensitive material
     *
     * @phpstan-param array<string, mixed> $grant
     * @phpstan-param list<array<string, mixed>> $audit
     */
    private function assertRedacted(array $grant, array $audit): void
    {
        $status = $this->container->get(QueryBus::class)->fetch(
            new FindCredentialDeliveryStatus('activation', $grant['delivery_id'])
        );
        $visible = $this->output.$this->error.json_encode(
            [$audit, $this->events, $status->toArray()],
            JSON_THROW_ON_ERROR
        );
        $secrets = [
            self::RAW_MARKER, $grant['credential_digest'], $grant['delivery_ciphertext'],
            'private-provider-marker', 'https://private/', 'SQLSTATE', 'Stack trace'
        ];
        foreach ($secrets as $secret) {
            self::assertStringNotContainsString($secret, $visible);
        }
    }
}
