<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Application\Bootstrap\BootstrapOperation;
use App\Application\Failure\PermissionDenied;
use App\Application\Invitation\ConsoleUserOperation;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Bootstrap\BootstrapClaims;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use App\Domain\Invitation\ConsoleInvitationInput;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\ActivationCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContextProvider;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\DeliverUserInvitation;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Event\RoleAssignedToUser;
use Fight\AccessControl\Domain\AccessControl\User\Event\UserInvited;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\AllEvents;
use Fight\Common\Domain\Messaging\Event\CommandFailedEvent;
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
 * Proves permanent PostgreSQL claims, actual independent package commits and competing console processes
 */
final class BootstrapTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    private AccessClock $clock;
    private int $providerCalls = 0;
    /** @var list<array<string, mixed>> */
    private array $events = [];

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
        $this->connection->executeStatement(
            'TRUNCATE installation_bootstrap, users, roles, permissions, audit_evidence CASCADE'
        );
        $this->clock = new AccessClock(new DateTimeImmutable('2026-10-15T12:00:00Z'));
        $this->container = $this->newContainer();
    }

    /**
     * Removes only owned failure injections and closes fixture connections
     */
    protected function tearDown(): void
    {
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
     * Establishes a pending elevated user, seven-day package grant and distinct mandatory audit after early delivery
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
        $claim = $this->connection->fetchAssociative('SELECT * FROM installation_bootstrap');
        self::assertIsArray($claim);
        self::assertSame((string) $command->userId, $claim['target_id']);
        self::assertSame((string) $command->input->operatorId, $claim['operator_id']);
        self::assertSame('first@example.test', $claim['email']);
        $grant = $this->connection->fetchAssociative('SELECT * FROM activation_grants');
        self::assertIsArray($grant);
        self::assertEquals($this->clock->now()->modify('+7 days'), new DateTimeImmutable($grant['expires_at']));
        self::assertSame('retry_pending', $grant['delivery_status']);
        self::assertSame(1, $this->providerCalls);
        $audit = $this->connection->fetchAllAssociative(
            'SELECT actor_id, action, subject_id, context FROM audit_evidence ORDER BY action'
        );
        self::assertSame([
            'user.bootstrap_claimed', 'user.bootstrap_completed', 'user.invitation_delivery.failed', 'user.invited'
        ], array_column($audit, 'action'));
        foreach ($audit as $row) {
            self::assertSame((string) $command->userId, $row['subject_id']);
            self::assertSame([], (array) json_decode($row['context'], true));
            $prefix = str_starts_with($row['action'], 'user.bootstrap_') ? 'bootstrap:' : 'console:';
            self::assertSame($prefix.$command->input->operatorId, $row['actor_id']);
        }
        self::assertSame('console:'.$command->input->operatorId, $this->events[0]['actor_id']);
        self::assertSame((string) $command->input->operatorId, $this->events[1]['actor_id']);
        self::assertNotSame((string) $command->userId, $this->events[1]['actor_id']);
        $safe = json_encode([$audit, $this->events, $this->bootstrap($this->command())], JSON_THROW_ON_ERROR);
        $secrets = ['delivery_ciphertext', 'credential_digest', 'private-provider-marker', 'Authorization:', 'SELECT '];
        foreach ($secrets as $secret) {
            self::assertStringNotContainsString($secret, $safe);
        }
        self::assertSame($claim, $this->connection->fetchAssociative('SELECT * FROM installation_bootstrap'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));

        // Lost immediate events are recoverable through package due-work discovery, not a consumer queue.
        $this->clock->time = $this->clock->now()->modify('+1 minute');
        $worker = $this->newContainer();
        $due = $worker->get(QueryBus::class)->fetch(new FindDueCredentialDeliveries($this->clock->now(), 10));
        self::assertCount(1, $due);
        self::assertSame($grant['delivery_id'], $due[0]->getDeliveryId()->toString());
        $worker->get(SynchronousCommandBus::class)->execute(new DeliverUserInvitation(
            'credential-recovery',
            $command->userId,
            $due[0]->getDeliveryId()
        ));
        self::assertSame(2, $this->providerCalls);
    }

    /**
     * Rolls back only the failed claim transaction and permits a legitimate fresh invocation
     */
    public function testClaimAuditFailureAllowsRetry(): void
    {
        $this->connection->executeStatement(
            "ALTER TABLE audit_evidence ADD CONSTRAINT bootstrap_test_abort CHECK (action <> 'user.bootstrap_claimed')"
        );
        self::assertSame('absent', $this->bootstrap($this->command())->claim);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        $this->connection->executeStatement('ALTER TABLE audit_evidence DROP CONSTRAINT bootstrap_test_abort');
        $this->container = $this->newContainer();
        self::assertTrue($this->bootstrap($this->command())->isComplete());
    }

    /**
     * Retains permanent claims and each independently committed step after concrete failures
     */
    #[DataProvider('failures')]
    public function testIndependentFailures(string $table, string $check, int $users, int $assignments): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE '.$table.' ADD CONSTRAINT bootstrap_test_abort CHECK ('.$check.')'
        );
        $command = $this->command();
        $state = $this->bootstrap($command);
        self::assertFalse($state->isComplete());
        self::assertSame('claimed', $state->claim);
        self::assertSame($users === 0 ? 'missing' : 'confirmed', $state->invitation);
        self::assertSame($assignments === 0 ? 'missing' : 'confirmed', $state->assignment);
        self::assertSame('unconfirmed', $state->audit);
        self::assertSame($users, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame($assignments, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
        self::assertSame($users, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
        $before = $this->connection->fetchAllAssociative('SELECT * FROM activation_grants');
        $this->connection->executeStatement('ALTER TABLE '.$table.' DROP CONSTRAINT bootstrap_test_abort');
        $this->container = $this->newContainer();
        self::assertSame('unavailable', $this->bootstrap($this->command())->claim);
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM activation_grants'));
        self::assertSame($users, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
    }

    /**
     * Supplies database-enforced failures rather than mocked package behavior
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
     * Inspects committed invitation and assignment after their own postcommit event exceptions
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
        $command = $this->command();
        $state = $this->bootstrap($command);
        self::assertSame('claimed', $state->claim);
        self::assertSame('confirmed', $state->invitation);
        self::assertSame($assignment, $state->assignment);
        self::assertSame('unconfirmed', $state->audit);
        self::assertFalse($state->isComplete());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
        $this->container = $this->newContainer();
        self::assertSame('unavailable', $this->bootstrap($this->command())->claim);
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
     * Refuses existing assignments regardless of target lifecycle before consuming a new claim
     */
    #[DataProvider('lifecycles')]
    public function testPriorAssignment(string $state): void
    {
        $command = $this->command();
        self::assertTrue($this->bootstrap($command)->isComplete());
        // Emulate an installation predating bootstrap tracking without offering any product reset path.
        $this->connection->executeStatement('TRUNCATE installation_bootstrap');
        $this->connection->executeStatement('UPDATE users SET state = ?, password_hash = ? WHERE id = ?', [
            $state,
            $state === 'pending_activation' ? null : password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            (string) $command->userId
        ]);
        $this->container = $this->newContainer();
        self::assertSame('absent', $this->bootstrap($this->command())->claim);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM installation_bootstrap'));
    }

    /**
     * Supplies all authoritative assignment lifecycle classes
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
     * Keeps the claim closed even after every role assignment is removed
     */
    public function testRoleRemovalDoesNotReopen(): void
    {
        self::assertTrue($this->bootstrap($this->command())->isComplete());
        $this->connection->executeStatement('DELETE FROM user_role_assignments');
        self::assertSame('unavailable', $this->bootstrap($this->command())->claim);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
    }

    /**
     * Rejects transformed invitation or assignment actors and targets at the actual routed boundary
     */
    #[DataProvider('transformations')]
    public function testTransformedPayload(string $kind): void
    {
        $filter = $this->createStub(CommandFilter::class);
        $filter->method('process')->willReturnCallback(static function (
            CommandMessage $message,
            callable $next
        ) use ($kind): void {
            $command = $message->payload();
            if (str_starts_with($kind, 'invite') && $command instanceof InvitePendingUser) {
                $message = CommandMessage::create(new InvitePendingUser(
                    $kind === 'invite_actor' ? 'console:'.UserId::generate() : $command->getActorId(),
                    $kind === 'invite_target' ? UserId::generate() : $command->getUserId(),
                    $kind === 'invite_email' ? EmailAddress::fromString('wrong@example.test') : $command->getEmail()
                ));
            }
            if (str_starts_with($kind, 'assign') && $command instanceof AssignRoleToUser) {
                $message = CommandMessage::create(new AssignRoleToUser(
                    $kind === 'assign_actor' ? UserId::generate() : $command->getActorId(),
                    $kind === 'assign_target' ? UserId::generate() : $command->getTargetUserId(),
                    $kind === 'assign_role' ? RoleId::generate() : $command->getRoleId()
                ));
            }
            if ($kind === 'root' && $command instanceof BootstrapSuperAdmin) {
                $message = CommandMessage::create(new BootstrapSuperAdmin($command->input, UserId::generate()));
            }
            $next($message);
        });
        $this->container->get(SynchronousCommandBus::class)->addFilter($filter);
        $state = $this->bootstrap($this->command());
        self::assertSame($kind === 'root' ? 'absent' : 'claimed', $state->claim);
        self::assertFalse($state->isComplete());
        self::assertSame(
            str_starts_with($kind, 'assign') ? 1 : 0,
            (int) $this->connection->fetchOne('SELECT count(*) FROM users')
        );
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
    }

    /**
     * Supplies supported configurable transformation points
     *
     * @return iterable<array{string}>
     */
    public static function transformations(): iterable
    {
        $kinds = ['root', 'invite_actor', 'invite_target', 'invite_email',
            'assign_actor', 'assign_target', 'assign_role'];
        foreach ($kinds as $kind) {
            yield [$kind];
        }
    }

    /**
     * Rejects a second target introduced by another scoped console operation inside a configured filter
     */
    public function testNestedConsoleCannotBorrowBootstrapClaim(): void
    {
        $other = new InviteOrdinaryUser(
            new ConsoleInvitationInput((string) UserId::generate(), 'other@example.test'),
            UserId::generate()
        );
        $ordinary = $this->container->get(ConsoleUserOperation::class);
        $nested = null;
        $attempted = false;
        $filter = $this->createStub(CommandFilter::class);
        $filter->method('process')->willReturnCallback(static function (
            CommandMessage $message,
            callable $next
        ) use (
            $ordinary,
            $other,
            &$nested,
            &$attempted
        ): void {
            if (!$attempted && $message->payload() instanceof InvitePendingUser) {
                $attempted = true;
                $nested = $ordinary->run($other);
            }
            $next($message);
        });
        $this->container->get(SynchronousCommandBus::class)->addFilter($filter);
        $command = $this->command();
        $state = $this->bootstrap($command);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM users WHERE id = ?', [
            (string) $other->userId
        ]));
        self::assertSame('claimed', $state->claim);
        self::assertFalse($state->isComplete());
        self::assertNotNull($nested);
        self::assertFalse($nested->success);
        self::assertSame([], $this->connection->fetchFirstColumn('SELECT id FROM users'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
    }

    /**
     * Detects silent configurable vetoes without claiming success or proceeding past a missing step
     */
    #[DataProvider('vetoes')]
    public function testSilentVeto(string $class, string $claim, int $users): void
    {
        $filter = $this->createStub(CommandFilter::class);
        $filter->method('process')->willReturnCallback(static function (
            CommandMessage $message,
            callable $next
        ) use ($class): void {
            if (!$message->payload() instanceof $class) {
                $next($message);
            }
        });
        $this->container->get(SynchronousCommandBus::class)->addFilter($filter);
        $state = $this->bootstrap($this->command());
        self::assertFalse($state->isComplete());
        self::assertSame($claim, $state->claim);
        self::assertSame($users, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
    }

    /**
     * Supplies every required dispatch boundary that a configured filter may withhold
     *
     * @return iterable<array{class-string, string, int}>
     */
    public static function vetoes(): iterable
    {
        yield [BootstrapSuperAdmin::class, 'absent', 0];
        yield [ReconcileManagedPolicy::class, 'absent', 0];
        yield [InvitePendingUser::class, 'claimed', 0];
        yield [AssignRoleToUser::class, 'claimed', 1];
    }

    /**
     * Competes real console processes at the guarded claim boundary without timing sleeps
     */
    public function testConcurrentConsoleClaims(): void
    {
        $normal = require dirname(__DIR__, 3).'/config/services.php';
        $normal->set(Connection::class, fn(): Connection => $this->connection);
        $normal->get(SynchronousCommandBus::class)->execute(
            new ReconcileManagedPolicy(InstallationManagedPolicy::create())
        );
        $this->connection->beginTransaction();
        $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24121, 1)');
        $children = [];
        try {
            foreach (['one', 'two'] as $name) {
                $pipes = [];
                $environment = getenv();
                $environment['DATABASE_URL'] = (string) getenv('TEST_DATABASE_URL');
                $process = proc_open([
                    PHP_BINARY, dirname(__DIR__, 3).'/scripts/console.php', 'user:bootstrap-super-admin',
                    '--internal', '--confirm-bootstrap', '--operator='.UserId::generate(),
                    '--email='.$name.'@example.test'
                ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, env_vars: $environment);
                self::assertIsResource($process);
                fclose($pipes[0]);
                $children[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 15;
            do {
                $waiting = (int) $this->connection->fetchOne(
                    <<<'SQL'
SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND classid = 24121 AND objid = 1 AND NOT granted
SQL
                );
            } while ($waiting < 2 && microtime(true) < $deadline);
            self::assertSame(2, $waiting, 'Both independent invocations must reach the claim guard.');
            $this->connection->commit();
            $exits = [];
            foreach ($children as [$process, $pipes]) {
                stream_set_timeout($pipes[1], 15);
                stream_set_timeout($pipes[2], 15);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exits[] = proc_close($process);
                self::assertStringNotContainsString('SQL', $stdout.$stderr);
                self::assertStringNotContainsString('credential', $stdout.$stderr);
            }
            $children = [];
            sort($exits);
            self::assertSame([0, 1], $exits);
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM installation_bootstrap'));
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            foreach ($children as [$process, $pipes]) {
                proc_terminate($process);
                foreach ([$pipes[1], $pipes[2]] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($process);
            }
        }
    }

    /**
     * Retains a committed claim across abrupt process exit before invitation and refuses a new target
     */
    public function testInterruptedClaimInFreshProcess(): void
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$c = require 'config/services.php';
$configure = require 'config/application/bootstrap_super_admin.php';
$configure($c);
$filter = new class implements \Fight\Common\Application\Messaging\Command\CommandFilter {
    public function process(
        \Fight\Common\Domain\Messaging\Command\CommandMessage $message,
        callable $next
    ): void {
        if ($message->payload() instanceof \Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser) {
            exit(73);
        }
        $next($message);
    }
};
$c->get(\Fight\Common\Application\Messaging\Command\SynchronousCommandBus::class)->addFilter($filter);
$operation = $c->get(\App\Application\Bootstrap\BootstrapOperation::class);
$operation->run(new \App\Domain\Bootstrap\Command\BootstrapSuperAdmin(
    new \App\Domain\Invitation\ConsoleInvitationInput(
        (string) \Fight\AccessControl\Domain\AccessControl\User\UserId::generate(), 'interrupted@example.test'
    ),
    \Fight\AccessControl\Domain\AccessControl\User\UserId::generate()
));
PHP;
        $environment = getenv();
        $environment['DATABASE_URL'] = (string) getenv('TEST_DATABASE_URL');
        $process = proc_open(
            [PHP_BINARY, '-r', $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            $environment
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        self::assertSame('', stream_get_contents($pipes[1]));
        self::assertSame('', stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(73, proc_close($process));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM installation_bootstrap'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        $this->container = $this->newContainer();
        self::assertSame('unavailable', $this->bootstrap($this->command())->claim);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
    }

    /**
     * Resolves lost commit acknowledgements by fresh state without retrying claim or completion mutations
     */
    #[DataProvider('lostAcknowledgements')]
    public function testLostAcknowledgement(string $stage): void
    {
        $actual = $this->container->get(BootstrapClaims::class);
        $claims = $this->createStub(BootstrapClaims::class);
        $claims->method('claim')->willReturnCallback(static function (BootstrapSuperAdmin $command) use (
            $actual,
            $stage
        ): void {
            $actual->claim($command);
            if ($stage === 'claim') {
                throw new RuntimeException('private-ack-marker');
            }
        });
        $claims->method('complete')->willReturnCallback(static function (
            BootstrapSuperAdmin $command
        ) use ($actual): void {
            $actual->complete($command);
            throw new RuntimeException('private-ack-marker');
        });
        $claims->method('inspect')->willReturnCallback($actual->inspect(...));
        $this->container->set(BootstrapClaims::class, static fn(): BootstrapClaims => $claims);
        $state = $this->bootstrap($this->command());
        self::assertSame('claimed', $state->claim);
        self::assertSame($stage === 'completion', $state->isComplete());
        self::assertSame($stage === 'claim' ? 0 : 1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM installation_bootstrap'));
        self::assertSame(
            'unavailable',
            $this->newContainer()->get(BootstrapOperation::class)->run($this->command())->claim
        );
    }

    /**
     * Supplies both owned independent commit acknowledgement boundaries
     *
     * @return iterable<array{string}>
     */
    public static function lostAcknowledgements(): iterable
    {
        yield ['claim'];
        yield ['completion'];
    }

    /**
     * Retains originating bootstrap state when package delivery claim or outcome transactions fail
     */
    #[DataProvider('deliveryFailures')]
    public function testDeliveryTransactionFailure(string $check, string $status, int $calls): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE activation_grants ADD CONSTRAINT bootstrap_test_abort CHECK ('.$check.')'
        );
        $command = $this->command();
        $state = $this->bootstrap($command);
        self::assertSame('claimed', $state->claim);
        self::assertSame('confirmed', $state->invitation);
        self::assertSame('missing', $state->assignment);
        self::assertSame('unconfirmed', $state->audit);
        self::assertSame($status, $this->connection->fetchOne('SELECT delivery_status FROM activation_grants'));
        self::assertSame($calls, $this->providerCalls);
        $this->connection->executeStatement('ALTER TABLE activation_grants DROP CONSTRAINT bootstrap_test_abort');
        $this->clock->time = $this->clock->now()->modify('+6 minutes');
        $worker = $this->newContainer();
        $due = $worker->get(QueryBus::class)->fetch(new FindDueCredentialDeliveries($this->clock->now(), 10));
        self::assertCount(1, $due);
        $worker->get(SynchronousCommandBus::class)->execute(new DeliverUserInvitation(
            'credential-recovery',
            $due[0]->getUserId(),
            $due[0]->getDeliveryId()
        ));
        self::assertSame('retry_pending', $this->connection->fetchOne('SELECT delivery_status FROM activation_grants'));
        self::assertSame('unavailable', $worker->get(BootstrapOperation::class)->run($this->command())->claim);
    }

    /**
     * Supplies independent delivery bookkeeping failures
     *
     * @return iterable<array{string, string, int}>
     */
    public static function deliveryFailures(): iterable
    {
        yield ["delivery_status <> 'claimed'", 'pending', 0];
        yield ["delivery_status <> 'retry_pending'", 'claimed', 1];
    }

    /**
     * Keeps real console output, safe events and persisted failure evidence free of seeded material
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
            $this->events,
            $this->connection->fetchAllAssociative('SELECT * FROM audit_evidence'),
            $this->connection->fetchOne('SELECT delivery_last_failure FROM activation_grants')
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
     * Rejects ambient transaction composition without consuming bootstrap
     */
    public function testNoOuterTransaction(): void
    {
        $this->connection->beginTransaction();
        try {
            $this->container->get(BootstrapClaims::class)->claim($this->command());
            self::fail('Ambient transaction must refuse.');
        } catch (\LogicException) {
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM installation_bootstrap'));
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Inspects unknown/missing/wrong target state safely through the independent read capability
     */
    public function testSafeInspection(): void
    {
        $command = $this->command();
        $claims = $this->container->get(BootstrapClaims::class);
        $claims->claim($command);
        self::assertSame('missing', $claims->inspect($command)->invitation);
        $this->connection->executeStatement('DELETE FROM audit_evidence');
        self::assertSame('unknown', $claims->inspect($command)->claim);
    }

    /**
     * Enforces fresh authenticated assignment authority through the normal bus and real PostgreSQL repositories
     */
    public function testNormalAuthenticatedAssignment(): void
    {
        $command = $this->command();
        self::assertTrue($this->bootstrap($command)->isComplete());
        $normal = require dirname(__DIR__, 3).'/config/services.php';
        $normal->set(Connection::class, fn(): Connection => $this->connection);
        $normal->set(Clock::class, fn(): Clock => $this->clock);
        $users = $normal->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
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
        $assignment = new AssignRoleToUser(
            $actor->getId(),
            $target->getId(),
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID)
        );
        $bus = $normal->get(SynchronousCommandBus::class);
        $bus->execute($assignment);
        $assigned = $users->getById($target->getId());
        self::assertInstanceOf(User::class, $assigned);
        self::assertTrue($assigned->hasRole($assignment->getRoleId()));
        // This is a current authority change after a successful projection in the same shared graph.
        $this->connection->executeStatement(<<<'SQL'
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = 'ASSIGN_SUPER_ADMIN')
SQL);
        try {
            $bus->execute($assignment);
            self::fail('Removed current elevation permission must refuse.');
        } catch (PermissionDenied $error) {
            self::assertNull($error->getPrevious());
        }
        $bus->execute(new ReconcileManagedPolicy(InstallationManagedPolicy::create()));
        $second = User::invite(
            UserId::generate(),
            EmailAddress::fromString('second-target@example.test'),
            $this->clock->now()
        );
        $this->connection->transactional(fn() => $users->add($second));
        $wrongActor = new AssignRoleToUser(UserId::generate(), $second->getId(), $assignment->getRoleId());
        $attempted = false;
        $nestedDenied = false;
        $filter = $this->createStub(CommandFilter::class);
        $filter->method('process')->willReturnCallback(static function (
            CommandMessage $message,
            callable $next
        ) use (
            $bus,
            $wrongActor,
            &$attempted,
            &$nestedDenied
): void {
            if (!$attempted) {
                $attempted = true;
                try {
                    $bus->execute($wrongActor);
                } catch (PermissionDenied) {
                    $nestedDenied = true;
                }
            }
            $next(CommandMessage::create($wrongActor));
        });
        $bus->addFilter($filter);
        $failed = false;
        try {
            $bus->execute(new AssignRoleToUser($actor->getId(), $second->getId(), $assignment->getRoleId()));
        } catch (\Throwable) {
            $failed = true;
        }
        self::assertTrue($nestedDenied);
        self::assertTrue($failed);
        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM user_role_assignments WHERE user_id = ?',
            [(string) $second->getId()]
        ));
    }

    /**
     * Runs the owned orchestrator through its actual scoped bus composition
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
        $configure = require dirname(__DIR__, 3).'/config/application/bootstrap_super_admin.php';
        $configure($container);
        $container->set(Connection::class, fn(): Connection => $this->connection);
        $container->set(Clock::class, fn(): Clock => $this->clock);
        $generator = $this->createStub(ActivationCredentialGenerator::class);
        $generator->method('generate')->willReturn(
            ActivationCredential::fromString('bootstrap-private-credential-marker')
        );
        $container->set(ActivationCredentialGenerator::class, static fn(): ActivationCredentialGenerator => $generator);
        $provider = $this->createStub(CredentialDeliveryProvider::class);
        $provider->method('deliver')->willReturnCallback(function (
            CredentialDeliveryInvocation $invocation
        ): CredentialDeliveryOutcome {
            self::assertFalse($this->connection->isTransactionActive());
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM installation_bootstrap'));
            self::assertSame(1, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM audit_evidence WHERE action = 'user.bootstrap_claimed'"
            ));
            $this->providerCalls++;
            if ($this->providerCalls === 1) {
                self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM user_role_assignments'));
            }
            throw new RuntimeException('Authorization: private-provider-marker https://private/credential');
        });
        $container->set(CredentialDeliveryProvider::class, static fn(): CredentialDeliveryProvider => $provider);
        $container->get(EventDispatcher::class)->addHandler(ClassName::underscore(AllEvents::class), function (
            EventMessage $message
        ): void {
            if (
                !$message->payload() instanceof CommandFailedEvent
                && ($message->payload() instanceof UserInvited || $message->payload() instanceof RoleAssignedToUser)
            ) {
                self::assertFalse($this->connection->isTransactionActive());
                $this->events[] = $message->payload()->toArray();
            }
        });

        return $container;
    }

    /**
     * Creates bounded explicit operator and target values through their owning factory
     */
    private function command(): BootstrapSuperAdmin
    {
        return new BootstrapSuperAdmin(
            new ConsoleInvitationInput((string) UserId::generate(), 'first@example.test'),
            UserId::generate()
        );
    }
}
