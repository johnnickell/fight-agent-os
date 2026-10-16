<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Application\Authorization\AuthenticatedRoleAssignment;
use App\Application\Bootstrap\BootstrapOperation;
use App\Application\Failure\PermissionDenied;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Bootstrap\BootstrapProgress;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use App\Domain\Invitation\ConsoleInvitationInput;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\ActivationCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContextProvider;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\DeliverUserInvitation;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Event\RoleAssignedToUser;
use Fight\AccessControl\Domain\AccessControl\User\Event\UserInvited;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Messaging\Event\AllEvents;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\AccessClock;

/**
 * Proves manual bootstrap admission, independent package commits and authoritative authenticated assignment
 */
final class BootstrapTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    private AccessClock $clock;
    private string|false $previousDeliveryKey;
    private int $providerCalls = 0;
    /** @var list<array<string, mixed>> */
    private array $events = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->previousDeliveryKey = getenv('APP_CREDENTIAL_DELIVERY_KEY');
        // Fresh containers and real child processes share only this fixture-owned ephemeral key.
        putenv('APP_CREDENTIAL_DELIVERY_KEY='.bin2hex(random_bytes(32)));
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection((new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ]))->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement(
            'TRUNCATE installation_bootstrap, users, roles, permissions, audit_evidence CASCADE'
        );
        $this->clock = new AccessClock(new DateTimeImmutable('2026-10-15T12:00:00Z'));
        $this->container = $this->newContainer();
    }

    /**
     * Restores fixture-owned environment and removes only owned failure injections
     */
    protected function tearDown(): void
    {
        if ($this->previousDeliveryKey === false) {
            putenv('APP_CREDENTIAL_DELIVERY_KEY');
        } else {
            putenv('APP_CREDENTIAL_DELIVERY_KEY='.$this->previousDeliveryKey);
        }
        if (!isset($this->connection)) {
            return;
        }
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        foreach (['users', 'user_role_assignments', 'audit_evidence', 'activation_grants'] as $table) {
            $this->connection->executeStatement(
                'ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS bootstrap_test_abort'
            );
        }
        $this->connection->close();
    }

    /**
     * Establishes an attributed pending elevation after early delivery and retains recoverable package work
     */
    public function testSuccessAndRecoverableDelivery(): void
    {
        $command = $this->command();
        self::assertTrue($this->bootstrap($command)->isComplete());
        $user = $this->connection->fetchAssociative('SELECT * FROM users');
        self::assertIsArray($user);
        self::assertSame((string) $command->userId, $user['id']);
        self::assertSame('pending_activation', $user['state']);
        self::assertNull($user['password_hash']);
        self::assertSame([InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID], $this->connection->fetchFirstColumn(
            'SELECT role_id FROM user_role_assignments'
        ));
        $grant = $this->connection->fetchAssociative('SELECT * FROM activation_grants');
        self::assertIsArray($grant);
        self::assertEquals($this->clock->now()->modify('+7 days'), new DateTimeImmutable($grant['expires_at']));
        self::assertSame('retry_pending', $grant['delivery_status']);
        self::assertSame(1, $this->providerCalls);
        $audit = $this->connection->fetchAllAssociative(
            'SELECT actor_id, action, subject_id, context FROM audit_evidence ORDER BY action'
        );
        self::assertSame([
            'user.bootstrap_completed', 'user.invitation_delivery.failed', 'user.invited'
        ], array_column($audit, 'action'));
        foreach ($audit as $row) {
            self::assertSame((string) $command->userId, $row['subject_id']);
            self::assertSame([], (array) json_decode($row['context'], true));
            $prefix = $row['action'] === 'user.bootstrap_completed' ? 'bootstrap:' : 'console:';
            self::assertSame($prefix.$command->input->operatorId, $row['actor_id']);
        }
        self::assertSame('console:'.$command->input->operatorId, $this->events[0]['actor_id']);
        self::assertSame((string) $command->input->operatorId, $this->events[1]['actor_id']);
        self::assertSame('refused', $this->bootstrap($this->command())->admission);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));

        $this->clock->time = $this->clock->now()->modify('+1 minute');
        $worker = $this->newContainer();
        $due = $worker->get(QueryBus::class)->fetch(new FindDueCredentialDeliveries($this->clock->now(), 10));
        self::assertCount(1, $due);
        self::assertSame($grant['delivery_id'], $due[0]->getDeliveryId()->toString());
        $worker->get(CommandBus::class)->execute(new DeliverUserInvitation(
            'credential-recovery',
            $command->userId,
            $due[0]->getDeliveryId()
        ));
        self::assertSame(2, $this->providerCalls);
    }

    /**
     * Retains independently committed steps and admits a later manual request only without Super Admin
     */
    #[DataProvider('failures')]
    public function testIndependentFailures(string $table, string $check, int $users, int $assignments): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE '.$table.' ADD CONSTRAINT bootstrap_test_abort CHECK ('.$check.')'
        );
        $state = $this->bootstrap($this->command());
        self::assertFalse($state->isComplete());
        self::assertSame('admitted', $state->admission);
        self::assertSame($users === 0 ? 'missing' : 'confirmed', $state->invitation);
        self::assertSame($assignments === 0 ? 'missing' : 'confirmed', $state->assignment);
        self::assertSame('unconfirmed', $state->audit);
        self::assertSame($users, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
        $before = $this->connection->fetchAllAssociative('SELECT * FROM activation_grants');
        $this->connection->executeStatement('ALTER TABLE '.$table.' DROP CONSTRAINT bootstrap_test_abort');
        $this->container = $this->newContainer();
        // Same email is a normal conflict, not automatic resend/resume of a retained invitation.
        $retry = $this->bootstrap($this->command());
        self::assertSame($assignments === 0 ? 'admitted' : 'refused', $retry->admission);
        self::assertSame($users === 0, $retry->isComplete());
        if ($users !== 0) {
            self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM activation_grants'));
        }
    }

    /**
     * Supplies real database failures at the invitation, assignment and separate audit boundaries
     *
     * @return iterable<array{string, string, int, int}>
     */
    public static function failures(): iterable
    {
        yield ['users', 'FALSE', 0, 0];
        yield ['user_role_assignments', 'FALSE', 1, 0];
        yield ['audit_evidence', "action <> 'user.bootstrap_completed'", 1, 1];
        yield ['activation_grants', 'FALSE', 0, 0];
    }

    /**
     * Allows a fresh manual target after partial failure without changing retained invitations or historical evidence
     */
    public function testPartialAttemptAndHistoricalClaimDoNotBlockFreshProcess(): void
    {
        $old = $this->command();
        $this->connection->insert('installation_bootstrap', [
            'singleton'   => true,
            'operator_id' => (string) $old->input->operatorId,
            'target_id'   => (string) $old->userId,
            'email'       => 'historical@example.test'
        ]);
        $this->connection->insert('audit_evidence', [
            'actor_id'     => 'bootstrap:'.$old->input->operatorId,
            'action'       => 'user.bootstrap_claimed',
            'subject_id'   => (string) $old->userId,
            'subject_type' => 'user',
            'context'      => '{}'
        ]);
        $history = $this->connection->fetchAllAssociative('SELECT * FROM installation_bootstrap');
        $audit = $this->connection->fetchAssociative(
            "SELECT * FROM audit_evidence WHERE action = 'user.bootstrap_claimed'"
        );
        $this->connection->executeStatement(
            'ALTER TABLE user_role_assignments ADD CONSTRAINT bootstrap_test_abort CHECK (FALSE)'
        );
        $first = $this->command();
        self::assertSame('missing', $this->bootstrap($first)->assignment);
        $grant = $this->connection->fetchAssociative(
            'SELECT * FROM activation_grants WHERE user_id = ?',
            [(string) $first->userId]
        );
        $this->connection->executeStatement('ALTER TABLE user_role_assignments DROP CONSTRAINT bootstrap_test_abort');
        $environment = getenv();
        $environment['APP_ENV'] = 'test';
        $environment['DATABASE_URL'] = (string) getenv('TEST_DATABASE_URL');
        $process = proc_open([
            PHP_BINARY, dirname(__DIR__, 3).'/scripts/console.php', 'user:bootstrap-super-admin',
            '--internal', '--confirm-bootstrap', '--operator='.UserId::generate(), '--email=fresh@example.test'
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, env_vars: $environment);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        self::assertSame('bootstrap_complete', json_decode($stdout, true)['status']);
        self::assertSame('', $stderr);
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame($grant, $this->connection->fetchAssociative(
            'SELECT * FROM activation_grants WHERE user_id = ?',
            [(string) $first->userId]
        ));
        self::assertSame($history, $this->connection->fetchAllAssociative('SELECT * FROM installation_bootstrap'));
        self::assertSame($audit, $this->connection->fetchAssociative(
            "SELECT * FROM audit_evidence WHERE action = 'user.bootstrap_claimed'"
        ));
    }

    /**
     * Inspects committed invitation or assignment after a package postcommit event exception
     */
    #[DataProvider('eventFailures')]
    public function testPostcommitFailure(string $event, string $assignment): void
    {
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore($event),
            static function (): never {
                throw new RuntimeException('SQL password=private-event-marker https://private/credential');
            }
        );
        $state = $this->bootstrap($this->command());
        self::assertSame('confirmed', $state->invitation);
        self::assertSame($assignment, $state->assignment);
        self::assertSame('unconfirmed', $state->audit);
        self::assertFalse($state->isComplete());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
    }

    /**
     * Supplies both independently committed business event boundaries
     *
     * @return iterable<array{class-string, string}>
     */
    public static function eventFailures(): iterable
    {
        yield [UserInvited::class, 'missing'];
        yield [RoleAssignedToUser::class, 'confirmed'];
    }

    /**
     * Counts existing assignments regardless of target lifecycle
     */
    #[DataProvider('lifecycles')]
    public function testPriorAssignment(string $state): void
    {
        $command = $this->command();
        self::assertTrue($this->bootstrap($command)->isComplete());
        $this->connection->executeStatement('UPDATE users SET state = ?, password_hash = ? WHERE id = ?', [
            $state, $state === 'pending_activation' ? null : password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            (string) $command->userId
        ]);
        $this->container = $this->newContainer();
        self::assertSame('refused', $this->bootstrap($this->command())->admission);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
    }

    /**
     * Supplies authoritative assignment lifecycle classes
     *
     * @return iterable<array{string}>
     */
    public static function lifecycles(): iterable
    {
        foreach (['pending_activation', 'active', 'disabled', 'deleted'] as $state) {
            yield [$state];
        }
    }

    /**
     * Retains originating state through independent delivery bookkeeping failure and later normal recovery
     */
    #[DataProvider('deliveryFailures')]
    public function testDeliveryTransactionFailure(string $check, string $status, int $calls): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE activation_grants ADD CONSTRAINT bootstrap_test_abort CHECK ('.$check.')'
        );
        $state = $this->bootstrap($this->command());
        self::assertSame('confirmed', $state->invitation);
        self::assertSame('missing', $state->assignment);
        self::assertSame($status, $this->connection->fetchOne('SELECT delivery_status FROM activation_grants'));
        self::assertSame($calls, $this->providerCalls);
        $this->connection->executeStatement('ALTER TABLE activation_grants DROP CONSTRAINT bootstrap_test_abort');
        $this->clock->time = $this->clock->now()->modify('+6 minutes');
        $worker = $this->newContainer();
        $due = $worker->get(QueryBus::class)->fetch(new FindDueCredentialDeliveries($this->clock->now(), 10));
        self::assertCount(1, $due);
        $worker->get(CommandBus::class)->execute(new DeliverUserInvitation(
            'credential-recovery',
            $due[0]->getUserId(),
            $due[0]->getDeliveryId()
        ));
        self::assertSame('retry_pending', $this->connection->fetchOne('SELECT delivery_status FROM activation_grants'));
    }

    /**
     * Supplies independent package delivery claim and outcome failures
     *
     * @return iterable<array{string, string, int}>
     */
    public static function deliveryFailures(): iterable
    {
        yield ["delivery_status <> 'claimed'", 'pending', 0];
        yield ["delivery_status <> 'retry_pending'", 'claimed', 1];
    }

    /**
     * Keeps actual Console output, events and audit free of credential/provider diagnostics
     */
    public function testConsoleRedaction(): void
    {
        $output = new BufferedOutput();
        self::assertSame(0, $this->container->get(SafeConsoleApplication::class)->run(new ArgvInput([
            'console', 'user:bootstrap-super-admin', '--internal', '--confirm-bootstrap',
            '--operator='.UserId::generate(), '--email=console@example.test', '-vvv'
        ]), $output));
        $text = $output->fetch();
        self::assertSame('bootstrap_complete', json_decode($text, true)['status']);
        $safe = $text.json_encode([
            $this->events, $this->connection->fetchAllAssociative('SELECT * FROM audit_evidence')
        ], JSON_THROW_ON_ERROR);
        $grant = $this->connection->fetchAssociative('SELECT * FROM activation_grants');
        self::assertIsArray($grant);
        foreach (
            ['bootstrap-private-credential-marker', 'private-provider-marker', 'Authorization:',
            'https://private/credential', $grant['credential_digest'], $grant['delivery_ciphertext']] as $marker
        ) {
            self::assertIsString($marker);
            self::assertNotSame('', $marker);
            self::assertStringNotContainsString($marker, $safe);
        }
    }

    /**
     * Enforces fresh authenticated permissions through real repositories and dispatches package assignment
     */
    public function testNormalAuthenticatedAssignment(): void
    {
        $command = $this->command();
        self::assertTrue($this->bootstrap($command)->isComplete());
        $normal = $this->newContainer();
        $users = $normal->get(UserRepository::class);
        $pending = $users->getById($command->userId);
        self::assertInstanceOf(User::class, $pending);
        $actor = clone $pending;
        $actor->activate(
            PasswordHash::fromString((string) password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT)),
            $this->clock->now()
        );
        $actor->advanceAuthenticationAuthorityRevision();
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $actor->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $this->clock->now(),
            $this->clock->now()->modify('+1 day'),
            $this->clock->now()->modify('+2 days'),
            $actor->getAuthenticationVersion(),
            false
        );
        $target = User::invite(
            UserId::generate(),
            EmailAddress::fromString('normal-target@example.test'),
            $this->clock->now()
        );
        $this->connection->transactional(function () use ($users, $pending, $actor, $session, $target): void {
            self::assertTrue($users->replaceAuthenticationAuthorityAndAddRefreshSession($pending, $actor, $session));
            $users->add($target);
        });
        $context = $this->createStub(AuthenticationContextProvider::class);
        $context->method('getAuthenticationContext')->willReturn(new AuthenticationContext(
            $actor->getId(),
            $session->getId(),
            $actor->getAuthenticationVersion()
        ));
        $normal->set(AuthenticationContextProvider::class, static fn(): AuthenticationContextProvider => $context);
        // Validate authentication at the same time used to create the session, not at wall-clock time.
        $normal->set(
            AuthoritativePrincipalResolver::class,
            fn(Container $container): AuthoritativePrincipalResolver => new AuthoritativePrincipalResolver(
                $container->get(UserRepository::class),
                $container->get(RefreshSessionRepository::class),
                $container->get(RoleRepository::class),
                $container->get(PermissionRepository::class),
                $this->clock
            )
        );
        $assignment = new AssignRoleToUser(
            $actor->getId(),
            $target->getId(),
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID)
        );
        $useCase = $normal->get(AuthenticatedRoleAssignment::class);
        $useCase->assign($assignment);
        self::assertTrue($users->getById($target->getId())->hasRole($assignment->getRoleId()));
        $this->connection->executeStatement(<<<'SQL'
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = 'ASSIGN_SUPER_ADMIN')
SQL);
        $this->expectException(PermissionDenied::class);
        $useCase->assign($assignment);
    }

    /**
     * Confirms durable completion after acknowledgement loss without replaying mutations
     */
    public function testLostCompletionAcknowledgement(): void
    {
        $actual = $this->container->get(BootstrapProgress::class);
        $progress = $this->createStub(BootstrapProgress::class);
        $progress->method('complete')->willReturnCallback(static function (BootstrapSuperAdmin $command) use (
            $actual
        ): void {
            $actual->complete($command);
            throw new RuntimeException('private-acknowledgement-marker');
        });
        $progress->method('inspect')->willReturnCallback($actual->inspect(...));
        $this->container->set(BootstrapProgress::class, static fn(): BootstrapProgress => $progress);
        self::assertTrue($this->bootstrap($this->command())->isComplete());
        self::assertSame(1, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM audit_evidence WHERE action = 'user.bootstrap_completed'"
        ));
    }

    /**
     * Inspects missing or changed target facts without inventing completion
     */
    public function testSafeInspection(): void
    {
        $command = $this->command();
        $progress = $this->container->get(BootstrapProgress::class);
        self::assertSame('missing', $progress->inspect($command)->invitation);
        try {
            $progress->complete($command);
            self::fail('Missing membership cannot complete.');
        } catch (\LogicException) {
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        }
        self::assertTrue($this->bootstrap($command)->isComplete());
        $this->connection->executeStatement("UPDATE users SET email = 'changed@example.test' WHERE id = ?", [
            (string) $command->userId
        ]);
        self::assertSame('unknown', $progress->inspect($command)->invitation);
    }

    /**
     * Runs the owned trusted operator use case
     */
    private function bootstrap(BootstrapSuperAdmin $command): \App\Domain\Bootstrap\BootstrapState
    {
        return $this->container->get(BootstrapOperation::class)->run($command);
    }

    /**
     * Creates independent invocation infrastructure on the guarded fixture connection
     */
    private function newContainer(): Container
    {
        $container = require dirname(__DIR__, 3).'/config/services.php';
        $container->set(Connection::class, fn(): Connection => $this->connection);
        $container->set(Clock::class, fn(): Clock => $this->clock);
        $generator = $this->createStub(ActivationCredentialGenerator::class);
        $generator->method('generate')->willReturn(
            ActivationCredential::fromString('bootstrap-private-credential-marker')
        );
        $container->set(ActivationCredentialGenerator::class, static fn(): ActivationCredentialGenerator => $generator);
        $provider = $this->createStub(CredentialDeliveryProvider::class);
        $provider->method('deliver')->willReturnCallback(function (): CredentialDeliveryOutcome {
            self::assertFalse($this->connection->isTransactionActive());
            $this->providerCalls++;
            if ($this->providerCalls === 1) {
                self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
            }
            throw new RuntimeException('Authorization: private-provider-marker https://private/credential');
        });
        $container->set(CredentialDeliveryProvider::class, static fn(): CredentialDeliveryProvider => $provider);
        $container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(AllEvents::class),
            function (EventMessage $message): void {
                if ($message->payload() instanceof UserInvited || $message->payload() instanceof RoleAssignedToUser) {
                    self::assertFalse($this->connection->isTransactionActive());
                    $this->events[] = $message->payload()->toArray();
                }
            }
        );

        return $container;
    }

    /**
     * Uses the owning ID factories for explicit operator and target values
     */
    private function command(): BootstrapSuperAdmin
    {
        return new BootstrapSuperAdmin(
            new ConsoleInvitationInput((string) UserId::generate(), 'first@example.test'),
            UserId::generate()
        );
    }
}
