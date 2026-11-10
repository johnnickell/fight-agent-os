<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Adapter\Persistence\Repository\PostgresEffectiveAdministrators;
use App\Adapter\Security\AccessToken\AccessJwt;
use App\Application\Authorization\PreventAdministratorLockout;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use App\Domain\Authorization\InstallationManagedPolicy;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\Command\DeleteUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\DisableUser;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AccessTokens;
use Tooling\OpenApi\Document;

/**
 * Exercises confirmed lifecycle transitions through real HTTP admission and package PostgreSQL effects
 */
final class UserLifecycleApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private AccessTokens $tokens;
    private User $actor;
    private string $token;
    /** @var array<string, string|false> */
    private array $environment = [];

    /**
     * Prepares only the guarded test database and the normal configured graph
     */
    protected function setUp(): void
    {
        foreach (['DATABASE_URL', 'APP_ACCESS_TOKEN_CONFIG', 'APP_CREDENTIAL_DELIVERY_KEY'] as $name) {
            $this->environment[$name] = getenv($name);
        }
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement('TRUNCATE users, roles, permissions, audit_evidence CASCADE');
        $tokens = $this->tokens = new AccessTokens();
        $tokens->clock->time = new DateTimeImmutable('2026-11-09T12:00:00Z');
        putenv('DATABASE_URL='.$url);
        putenv('APP_ACCESS_TOKEN_CONFIG='.json_encode($tokens->configuration, JSON_THROW_ON_ERROR));
        // Own the fixture cipher key; independent race workers inherit this ephemeral value.
        putenv('APP_CREDENTIAL_DELIVERY_KEY='.bin2hex(random_bytes(32)));
        $container = require dirname(__DIR__, 3).'/config/services.php';
        self::assertInstanceOf(Container::class, $container);
        $this->container = $container;
        $container->set(Clock::class, static fn(): Clock => $tokens->clock);
        $container->set(AccessJwt::class, static fn(): AccessJwt => $tokens->jwt);
        $container->set(AuthenticateAccessToken::class, static fn(Container $c) => new AuthenticateAccessToken(
            $tokens->jwt,
            $c->get(UserRepository::class),
            $c->get(RefreshSessionRepository::class),
            $tokens->clock
        ));
        $container->set(AuthoritativePrincipalResolver::class, static fn(Container $c) =>
            new AuthoritativePrincipalResolver(
                $c->get(UserRepository::class),
                $c->get(RefreshSessionRepository::class),
                $c->get(RoleRepository::class),
                $c->get(PermissionRepository::class),
                $tokens->clock
            ));
        AppFactory::setContainer($container);
        AppFactory::setResponseFactory(new ResponseFactory());
        /** @var App<Container> $app */
        $app = AppFactory::create();
        $container->set(App::class, static fn(): App => $app);
        require dirname(__DIR__, 3).'/config/routes.php';
        require dirname(__DIR__, 3).'/config/middleware.php';
        $this->app = $app;
        $container->get(CommandBus::class)->execute(new ReconcileManagedPolicy(InstallationManagedPolicy::create()));
        $this->actor = $this->user(true);
        $this->token = $this->tokenFor($this->actor);
    }

    /**
     * Releases fixture connections and process-local configuration
     */
    protected function tearDown(): void
    {
        $this->connection->close();
        $this->container->get(Connection::class)->close();
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
    }

    /**
     * Disables atomically with audit and session invalidation, then enables without restoring authority
     */
    public function testDisableEnableAndImmediateAuthorityEffects(): void
    {
        $target = $this->user(false);
        $token = $this->tokenFor($target);
        $this->check($this->request($target->getId(), 'disable'), 200, 'disable');
        $stored = $this->container->get(UserRepository::class)->getById($target->getId());
        self::assertNotNull($stored);
        self::assertSame('disabled', $stored->getState()->value);
        self::assertSame($target->getAuthenticationVersion() + 1, $stored->getAuthenticationVersion());
        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM refresh_sessions WHERE user_id = ? AND revoked = FALSE',
            [$target->getId()->toString()]
        ));
        $me = new ServerRequestFactory()->createServerRequest('GET', 'https://agent-os.test/api/v1/me');
        self::assertSame(401, $this->app->handle($me->withHeader('Authorization', 'Bearer '.$token))->getStatusCode());
        $this->check($this->request($target->getId(), 'enable'), 200, 'enable');
        $enabled = $this->container->get(UserRepository::class)->getById($target->getId());
        self::assertNotNull($enabled);
        self::assertSame('active', $enabled->getState()->value);
        self::assertSame($stored->getAuthenticationVersion(), $enabled->getAuthenticationVersion());
        self::assertSame(401, $this->app->handle($me->withHeader('Authorization', 'Bearer '.$token))->getStatusCode());
        $audit = $this->connection->fetchAllAssociative(
            "SELECT * FROM audit_evidence WHERE action IN ('user.disabled', 'user.enabled') ORDER BY action"
        );
        self::assertCount(2, $audit);
        self::assertStringNotContainsString($token, json_encode($audit, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString(
            $target->getPasswordHash()->toString(),
            json_encode($audit, JSON_THROW_ON_ERROR)
        );
        self::assertStringContainsString($this->actor->getId()->toString(), json_encode($audit, JSON_THROW_ON_ERROR));
        self::assertStringContainsString($target->getId()->toString(), json_encode($audit, JSON_THROW_ON_ERROR));
    }

    /**
     * Serializes actual same-target public commands without partial audit or resurrected authority
     *
     * @phpstan-param list<string> $outcomes
     * @phpstan-param list<string> $actions
     */
    #[DataProvider('competingTransitions')]
    public function testCompetingLifecycleCommands(
        bool $initiallyDisabled,
        string $first,
        string $second,
        array $outcomes,
        string $finalState,
        array $actions
    ): void {
        $target = $this->user(false);
        $token = $this->tokenFor($target);
        if ($initiallyDisabled) {
            $this->container->get(CommandBus::class)->execute(new DisableUser($this->actor->getId(), $target->getId()));
        }
        $before = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE id = ?',
            [$target->getId()->toString()]
        );
        self::assertIsArray($before);
        $this->connection->executeStatement('TRUNCATE audit_evidence');
        $children = [];
        $firstPid = 0;
        try {
            foreach ([$first, $second] as $index => $operation) {
                $pipes = [];
                $process = proc_open(
                    [
                        PHP_BINARY, '-r',
                        'require "vendor/autoload.php"; Tests\\Support\\UserLifecycleRaceWorker::run();'
                    ],
                    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 3)
                );
                self::assertIsResource($process);
                $children[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 15);
                fwrite($pipes[0], json_encode([
                    'actor'     => $this->actor->getId()->toString(),
                    'target'    => $target->getId()->toString(),
                    'operation' => $operation,
                    'time'      => $this->tokens->clock->now()->format('c'),
                    'pause'     => $index === 0
                ], JSON_THROW_ON_ERROR)."\n");
                fflush($pipes[0]);
                $pid = (int) trim((string) fgets($pipes[1]));
                self::assertGreaterThan(0, $pid);
                if ($index === 0) {
                    $firstPid = $pid;
                    self::assertSame('snapshot', trim((string) fgets($pipes[1])));
                } else {
                    // Observe the second executing command actually waiting on the first transaction, not a sleep.
                    $deadline = microtime(true) + 5;
                    do {
                        $blocked = $this->connection->fetchOne(
                            'SELECT ? = ANY(pg_blocking_pids(?))',
                            [$firstPid, $pid]
                        );
                        if (!$blocked) {
                            usleep(10000);
                        }
                    } while (!$blocked && microtime(true) < $deadline);
                    self::assertTrue($blocked, 'The competing command must reach the held per-user fence.');
                }
            }
            self::assertSame($before, $this->connection->fetchAssociative(
                'SELECT * FROM users WHERE id = ?',
                [$target->getId()->toString()]
            ));
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
            fwrite($children[0][1][0], "go\n");
            fflush($children[0][1][0]);
            foreach ($children as $index => [$process, $pipes]) {
                self::assertSame($outcomes[$index], trim((string) fgets($pipes[1])));
            }
            $stored = $this->container->get(UserRepository::class)->getById($target->getId());
            self::assertNotNull($stored);
            self::assertSame($finalState, $stored->getState()->value);
            self::assertSame(
                (int) $before['authentication_version'] + count(array_filter(
                    $actions,
                    static fn($action) => $action === 'user.disabled'
                )),
                $stored->getAuthenticationVersion()
            );
            self::assertSame(1, (int) $this->connection->fetchOne(
                'SELECT count(*) FROM refresh_sessions WHERE user_id = ? AND revoked = TRUE',
                [$target->getId()->toString()]
            ));
            self::assertSame(0, (int) $this->connection->fetchOne(
                'SELECT count(*) FROM refresh_sessions WHERE user_id = ? AND revoked = FALSE',
                [$target->getId()->toString()]
            ));
            $me = new ServerRequestFactory()->createServerRequest('GET', 'https://agent-os.test/api/v1/me');
            self::assertSame(
                401,
                $this->app->handle($me->withHeader('Authorization', 'Bearer '.$token))->getStatusCode()
            );
            $audit = $this->connection->fetchAllAssociative(
                'SELECT action, actor_id, subject_id, context FROM audit_evidence ORDER BY id'
            );
            self::assertSame($actions, array_column($audit, 'action'));
            foreach ($audit as $entry) {
                self::assertSame($this->actor->getId()->toString(), $entry['actor_id']);
                self::assertSame($target->getId()->toString(), $entry['subject_id']);
                self::assertSame('{}', $entry['context']);
            }
        } finally {
            foreach ($children as [$process, $pipes]) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                proc_close($process);
            }
        }
    }

    /**
     * Supplies opposing, duplicate and initially invalid intents in both lifecycle directions
     *
     * @return iterable<array{bool, string, string, list<string>, string, list<string>}>
     */
    public static function competingTransitions(): iterable
    {
        yield 'disable then enable' => [
            false, 'disable', 'enable', ['success', 'success'], 'active', ['user.disabled', 'user.enabled']
        ];
        yield 'enable then disable' => [
            true, 'enable', 'disable', ['success', 'success'], 'disabled', ['user.enabled', 'user.disabled']
        ];
        yield 'duplicate disable' => [
            false, 'disable', 'disable', ['success', 'conflict'], 'disabled', ['user.disabled']
        ];
        yield 'duplicate enable' => [
            true, 'enable', 'enable', ['success', 'conflict'], 'active', ['user.enabled']
        ];
        yield 'invalid enable first' => [
            false, 'enable', 'disable', ['conflict', 'success'], 'disabled', ['user.disabled']
        ];
        yield 'invalid disable first' => [
            true, 'disable', 'enable', ['conflict', 'success'], 'active', ['user.enabled']
        ];
    }

    /**
     * Rolls back lifecycle and session invalidation when required package audit cannot persist
     */
    public function testRequiredAuditFailureRollsBack(): void
    {
        $target = $this->user(false);
        $this->tokenFor($target);
        $audit = $this->createStub(AuditEvidenceRepository::class);
        $audit->method('add')->willThrowException(new \RuntimeException('private-audit-failure'));
        $this->container->set(AuditEvidenceRepository::class, static fn() => $audit);
        $this->check($this->request($target->getId(), 'disable'), 500, 'disable');
        $stored = $this->container->get(UserRepository::class)->getById($target->getId());
        self::assertNotNull($stored);
        self::assertSame('active', $stored->getState()->value);
        self::assertSame($target->getAuthenticationVersion(), $stored->getAuthenticationVersion());
        self::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM refresh_sessions WHERE user_id = ? AND revoked = FALSE',
            [$target->getId()->toString()]
        ));
        self::assertSame(0, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM audit_evidence WHERE action = 'user.disabled'"
        ));
    }

    /**
     * Requires each operation's own permission, not read, general update or the opposite operation
     */
    #[DataProvider('operations')]
    public function testExactPermissionAndFreshRevocation(string $operation, string $permission): void
    {
        $target = $this->user(false);
        if ($operation === 'enable') {
            $this->container->get(CommandBus::class)->execute(new DisableUser($this->actor->getId(), $target->getId()));
        } elseif ($operation === 'restore') {
            $this->container->get(CommandBus::class)->execute(new DeleteUser($this->actor->getId(), $target->getId()));
        }
        $this->connection->executeStatement(
            'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name <> ?)',
            [$permission]
        );
        $this->check($this->request($target->getId(), $operation, token: ''), 401, $operation);
        $this->check($this->request($target->getId(), $operation), 200, $operation);
        $before = (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence');
        $this->connection->executeStatement('DELETE FROM role_permissions');
        $this->check($this->request($target->getId(), $operation), 403, $operation);
        self::assertSame($before, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    /**
     * Rejects explicit-intent omissions and actor/target injection before package mutation
     */
    #[DataProvider('invalidBodies')]
    public function testInvalidInput(string $body): void
    {
        $target = $this->user(false);
        foreach (['disable', 'enable', 'delete', 'restore'] as $operation) {
            $this->check($this->request($target->getId(), $operation, $body), 400, $operation);
        }
        $stored = $this->container->get(UserRepository::class)->getById($target->getId());
        self::assertNotNull($stored);
        self::assertSame('active', $stored->getState()->value);
        self::assertSame(0, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM audit_evidence WHERE action LIKE 'user.%'"
        ));
    }

    /**
     * Supplies strict confirmation and authority-injection failures
     *
     * @return iterable<array{string}>
     */
    public static function invalidBodies(): iterable
    {
        foreach (
            ['{}', '{"confirm":false}', '{"confirm":"true"}', '{"confirm":true,"actor_id":"private"}',
            '{"confirm":true,"user_id":"private"}', '{"confirm":true,"expected_revision":"private"}'] as $body
        ) {
            yield [$body];
        }
    }

    /**
     * Supplies the independent lifecycle permissions
     *
     * @return iterable<array{string, string}>
     */
    public static function operations(): iterable
    {
        yield ['disable', 'DISABLE_USERS'];
        yield ['enable', 'ENABLE_USERS'];
        yield ['delete', 'DELETE_USERS'];
        yield ['restore', 'RESTORE_USERS'];
    }

    /**
     * Rejects self, missing and invalid-state targets without partial state or audit
     */
    public function testRejectedTargets(): void
    {
        $target = $this->user(false);
        $this->check($this->request($this->actor->getId(), 'disable'), 409, 'disable');
        $this->check($this->request($this->actor->getId(), 'enable'), 409, 'enable');
        $this->check($this->request($target->getId(), 'disable'), 200, 'disable');
        $this->check($this->request($target->getId(), 'disable'), 409, 'disable');
        self::assertSame(1, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM audit_evidence WHERE action = 'user.disabled'"
        ));
    }

    /**
     * Maps an unknown authorized target to a non-enumerating lifecycle conflict
     */
    public function testMissingTarget(): void
    {
        $this->check($this->request(UserId::generate(), 'disable'), 409, 'disable');
    }

    /**
     * Rejects enabling an already active target without any audit mutation
     */
    public function testInvalidEnableState(): void
    {
        $target = $this->user(false);
        $this->check($this->request($target->getId(), 'enable'), 409, 'enable');
        self::assertSame(0, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM audit_evidence WHERE action = 'user.enabled'"
        ));
    }

    /**
     * Qualifies last-admin observation and the explicitly accepted independent-precheck race
     */
    public function testLastAdministratorPrecheckHasNoConcurrentReservation(): void
    {
        $second = $this->user(true);
        $policy = $this->container->get(PreventAdministratorLockout::class);
        $query = new PostgresEffectiveAdministrators($this->connection);
        self::assertFalse($query->isLast($this->actor->getId()));
        $policy->assertCanDisable($this->actor->getId(), $second->getId());
        $policy->assertCanDisable($second->getId(), $this->actor->getId());
        $this->container->get(CommandBus::class)->execute(new DisableUser($this->actor->getId(), $second->getId()));
        self::assertTrue($query->isLast($this->actor->getId()));
        try {
            $policy->assertCanDisable($second->getId(), $this->actor->getId());
            self::fail('A fresh observed last-admin precheck must reject.');
        } catch (\Fight\Common\Application\Error\Exception\StateConflict) {
            self::assertTrue($query->isLast($this->actor->getId()));
        }
        // The previously admitted second operation is allowed to finish; there is deliberately no reservation.
        $this->container->get(CommandBus::class)->execute(new DisableUser($second->getId(), $this->actor->getId()));
        self::assertSame(0, (int) $this->connection->fetchOne("SELECT count(*) FROM users WHERE state = 'active'"));
    }

    /**
     * Deletes and restores under package ownership while keeping identity claims and retired sessions
     */
    public function testDeleteRestoreRetainsIdentityWithoutSessionRevival(): void
    {
        $target = $this->user(false);
        $token = $this->tokenFor($target);
        $this->check($this->request($target->getId(), 'delete'), 200, 'delete');
        $deleted = $this->container->get(UserRepository::class)->getById($target->getId());
        self::assertNotNull($deleted);
        self::assertSame(UserState::DELETED, $deleted->getState());
        self::assertSame($target->getAuthenticationVersion() + 1, $deleted->getAuthenticationVersion());
        self::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM user_email_claims WHERE user_id = ? AND email = ?',
            [$target->getId()->toString(), $target->getEmail()->canonical()]
        ));
        $this->check($this->request($target->getId(), 'restore'), 200, 'restore');
        $restored = $this->container->get(UserRepository::class)->getById($target->getId());
        self::assertNotNull($restored);
        self::assertSame(UserState::ACTIVE, $restored->getState());
        self::assertSame($deleted->getAuthenticationVersion(), $restored->getAuthenticationVersion());
        self::assertSame($target->getPasswordHash()?->toString(), $restored->getPasswordHash()?->toString());
        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM refresh_sessions WHERE user_id = ? AND revoked = FALSE',
            [$target->getId()->toString()]
        ));
        $me = new ServerRequestFactory()->createServerRequest('GET', 'https://agent-os.test/api/v1/me');
        self::assertSame(401, $this->app->handle($me->withHeader('Authorization', 'Bearer '.$token))->getStatusCode());
        $audit = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT actor_id, subject_id, action, context FROM audit_evidence
                WHERE action IN ('user.deleted', 'user.restored') ORDER BY id
                SQL
        );
        self::assertSame(['user.deleted', 'user.restored'], array_column($audit, 'action'));
        foreach ($audit as $entry) {
            self::assertSame($this->actor->getId()->toString(), $entry['actor_id']);
            self::assertSame($target->getId()->toString(), $entry['subject_id']);
            self::assertSame('{}', $entry['context']);
        }
    }

    /**
     * Rejects self deletion and unsupported restoration input without mutation
     */
    public function testDeleteRestoreEligibilityAndStrictInput(): void
    {
        $this->check($this->request($this->actor->getId(), 'delete'), 409, 'delete');
        $target = $this->user(false);
        $this->container->get(CommandBus::class)->execute(new DisableUser($this->actor->getId(), $target->getId()));
        $this->check($this->request($target->getId(), 'delete'), 200, 'delete');
        foreach (
            [
            '{"confirm":true}', '{"confirm":true,"restoration_state":"disabled"}',
            '{"confirm":true,"restoration_state":null}',
            '{"confirm":false,"restoration_state":"active"}',
            '{"confirm":true,"restoration_state":"active","actor_id":"private"}',
            '{"confirm":true,"restoration_state":"active","expected_revision":"private"}'
            ] as $body
        ) {
            $this->check($this->request($target->getId(), 'restore', $body), 400, 'restore');
        }
        $stored = $this->container->get(UserRepository::class)->getById($target->getId());
        self::assertNotNull($stored);
        self::assertSame(UserState::DELETED, $stored->getState());
    }

    /**
     * Issues fresh internal pending activation authority without disclosing it or issuing sessions
     */
    public function testPendingRestoration(): void
    {
        $target = $this->user(false);
        $this->tokenFor($target);
        $this->check($this->request($target->getId(), 'delete'), 200, 'delete');
        $this->terminalActivation($target);
        $this->check($this->request(
            $target->getId(),
            'restore',
            '{"confirm":true,"restoration_state":"pending_activation"}'
        ), 200, 'restore');
        $stored = $this->container->get(UserRepository::class)->getById($target->getId());
        self::assertNotNull($stored);
        self::assertSame(UserState::PENDING_ACTIVATION, $stored->getState());
        self::assertNull($stored->getPasswordHash());
        self::assertSame(2, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM activation_grants WHERE user_id = ?',
            [$target->getId()->toString()]
        ));
        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM refresh_sessions WHERE user_id = ? AND revoked = FALSE',
            [$target->getId()->toString()]
        ));
    }

    /**
     * Rejects malformed route IDs and query selectors before lifecycle execution
     */
    #[DataProvider('deletionOperations')]
    public function testDeletionRouteAndQueryAdmission(string $operation): void
    {
        foreach (['private', UserId::generate()->toString().'?actor_id=private'] as $target) {
            $path = '/api/v1/users/'.$target;
            if ($operation === 'restore') {
                $path = '/api/v1/users/'.explode('?', $target)[0].'/restore';
                if (str_contains($target, '?')) {
                    $path .= '?actor_id=private';
                }
            }
            $request = new ServerRequestFactory()->createServerRequest(
                $operation === 'delete' ? 'DELETE' : 'POST',
                'https://agent-os.test'.$path,
                ['REQUEST_URI' => $path]
            );
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withHeader('Authorization', 'Bearer '.$this->token);
            $body = $operation === 'delete' ? '{"confirm":true}' : '{"confirm":true,"restoration_state":"active"}';
            $request->getBody()->write($body);
            $this->check($this->app->handle($request), 400, $operation);
        }
        self::assertSame(0, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM audit_evidence WHERE action IN ('user.deleted', 'user.restored')"
        ));
    }

    /**
     * Rolls back required deletion and pending restoration audit failures on the shared connection
     */
    #[DataProvider('deletionOperations')]
    public function testDeletionAuditRollback(string $operation): void
    {
        $target = $this->user(false);
        $this->tokenFor($target);
        if ($operation === 'restore') {
            $this->check($this->request($target->getId(), 'delete'), 200, 'delete');
        }
        $this->terminalActivation($target);
        $before = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE id = ?',
            [$target->getId()->toString()]
        );
        $audit = $this->createStub(AuditEvidenceRepository::class);
        $audit->method('add')->willThrowException(new \RuntimeException('private-audit-failure'));
        $this->container->set(AuditEvidenceRepository::class, static fn() => $audit);
        $body = $operation === 'restore' ? '{"confirm":true,"restoration_state":"pending_activation"}' : null;
        $this->check($this->request($target->getId(), $operation, $body), 500, $operation);
        self::assertSame($before, $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE id = ?',
            [$target->getId()->toString()]
        ));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
        self::assertSame($operation === 'delete' ? 1 : 0, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM refresh_sessions WHERE user_id = ? AND revoked = FALSE',
            [$target->getId()->toString()]
        ));
    }

    /**
     * Preserves committed lifecycle and audit when post-commit notification fails
     */
    #[DataProvider('deletionOperations')]
    public function testDeletionPostCommitUncertainty(string $operation): void
    {
        $target = $this->user(false);
        if ($operation === 'restore') {
            $this->check($this->request($target->getId(), 'delete'), 200, 'delete');
        }
        $events = $this->createStub(EventDispatcher::class);
        $events->method('trigger')->willThrowException(new \RuntimeException('private-event-failure'));
        $this->container->set(EventDispatcher::class, static fn() => $events);
        $this->check($this->request($target->getId(), $operation), 500, $operation);
        self::assertSame($operation === 'delete' ? 'deleted' : 'active', $this->connection->fetchOne(
            'SELECT state FROM users WHERE id = ?',
            [$target->getId()->toString()]
        ));
        self::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM audit_evidence WHERE action = ?',
            ['user.'.($operation === 'delete' ? 'deleted' : 'restored')]
        ));
    }

    /**
     * Rejects a stale package-loaded deletion snapshot after a competing HTTP deletion commits
     */
    public function testCompetingDeletionPreservesCasAndSingleAudit(): void
    {
        $target = $this->user(false);
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', 'require "vendor/autoload.php"; Tests\\Support\\UserLifecycleRaceWorker::run();'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3)
        );
        self::assertIsResource($process);
        try {
            stream_set_timeout($pipes[1], 15);
            fwrite($pipes[0], json_encode([
                'actor'     => $this->actor->getId()->toString(),
                'target'    => $target->getId()->toString(),
                'operation' => 'delete',
                'time'      => $this->tokens->clock->now()->format('c'),
                'pause'     => true
            ], JSON_THROW_ON_ERROR)."\n");
            fflush($pipes[0]);
            self::assertGreaterThan(0, (int) fgets($pipes[1]));
            self::assertSame('snapshot', trim((string) fgets($pipes[1])));
            $this->check($this->request($target->getId(), 'delete'), 200, 'delete');
            fwrite($pipes[0], "go\n");
            fflush($pipes[0]);
            self::assertSame('conflict', trim((string) fgets($pipes[1])));
            self::assertSame(1, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM audit_evidence WHERE action = 'user.deleted'"
            ));
            self::assertSame($target->getAuthenticationVersion() + 1, (int) $this->connection->fetchOne(
                'SELECT authentication_version FROM users WHERE id = ?',
                [$target->getId()->toString()]
            ));
        } finally {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    /**
     * Seeds the consumed invitation authority that precedes a real activated identity
     */
    private function terminalActivation(User $user): void
    {
        $now = $this->tokens->clock->now();
        $credential = $this->container->get(
            \Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\ActivationCredentialGenerator::class
        )->generate();
        $grant = \Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant::issue(
            \Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin::PRIVATE_INVITATION,
            $user->getId(),
            $credential,
            $now,
            $now->modify('+7 days'),
            $user->getEmail(),
            $this->container->get(
                \Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher::class
            )->encrypt($credential->toString())
        );
        $repository = $this->container->get(
            \Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository::class
        );
        $this->container->get(Connection::class)->transactional(function () use ($repository, $grant, $now): void {
            self::assertTrue($repository->add($grant));
            self::assertTrue($repository->replace($grant, $grant->consume($now)));
        });
    }

    /**
     * Rejects package-ineligible targets without claiming rollback errors are reusable request containers
     */
    #[DataProvider('ineligibleDeletionTargets')]
    public function testIneligibleDeletionTarget(string $operation, string $state): void
    {
        $target = $this->user(false);
        $id = $target->getId();
        if ($state === 'missing') {
            $id = UserId::generate();
        } elseif ($state === 'pending') {
            $id = UserId::generate();
            $pending = User::invite($id, EmailAddress::fromString('pending@example.test'), $this->tokens->clock->now());
            $this->container->get(Connection::class)->transactional(
                fn() => $this->container->get(UserRepository::class)->add($pending)
            );
        } elseif ($state === 'deleted') {
            $this->check($this->request($id, 'delete'), 200, 'delete');
        }
        $before = $this->connection->fetchAllAssociative('SELECT * FROM users ORDER BY id');
        $auditCount = (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence');
        $this->check($this->request($id, $operation), 409, $operation);
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM users ORDER BY id'));
        self::assertSame($auditCount, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    /**
     * Supplies unsupported current-state transitions and unknown identities
     *
     * @return iterable<array{string, string}>
     */
    public static function ineligibleDeletionTargets(): iterable
    {
        yield ['delete', 'pending'];
        yield ['delete', 'deleted'];
        yield ['delete', 'missing'];
        yield ['restore', 'active'];
        yield ['restore', 'missing'];
    }

    /**
     * Supplies the soft deletion and restoration operations
     *
     * @return iterable<array{string}>
     */
    public static function deletionOperations(): iterable
    {
        yield ['delete'];
        yield ['restore'];
    }

    /**
     * Creates a real eligible identity with an optional managed administrator assignment
     */
    private function user(bool $administrator): User
    {
        $now = $this->tokens->clock->now()->modify('-1 minute');
        $id = UserId::generate();
        $user = User::invite($id, EmailAddress::fromString($id->toString().'@example.test'), $now);
        $user->activate(
            PasswordHash::fromString((string) password_hash('fixture-secret', PASSWORD_BCRYPT, ['cost' => 4])),
            $now
        );
        $roleId = InstallationManagedPolicy::USER_ROLE_ID;
        if ($administrator) {
            $roleId = InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID;
        }
        $user->assignRole(RoleId::fromString($roleId), $now);
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(UserRepository::class)->add($user)
        );

        return $user;
    }

    /**
     * Issues an actual signed token bound to a persisted session and identity
     */
    private function tokenFor(User $user): string
    {
        $now = $this->tokens->clock->now()->modify('-1 minute');
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            $user->getAuthenticationVersion(),
            false
        );
        $this->connection->transactional(fn() => RefreshSessionRecords::insert($this->connection, $session));
        $claims = $this->tokens->claims();
        $claims['sub'] = $user->getId()->toString();
        $claims['sid'] = $session->getId()->toString();
        $claims['auth_version'] = $user->getAuthenticationVersion();

        return $this->tokens->signed(json_encode($claims, JSON_THROW_ON_ERROR));
    }

    /**
     * Submits real JSON through normal authentication, validation, routes and buses
     */
    private function request(
        UserId $targetId,
        string $operation,
        ?string $body = null,
        ?string $token = null
    ): ResponseInterface {
        $path = '/api/v1/users/'.$targetId->toString().($operation === 'delete' ? '' : '/'.$operation);
        $body ??= $operation === 'restore' ? '{"confirm":true,"restoration_state":"active"}' : '{"confirm":true}';
        $request = new ServerRequestFactory()->createServerRequest(
            $operation === 'delete' ? 'DELETE' : 'POST',
            'https://agent-os.test'.$path,
            ['REQUEST_URI' => $path]
        );
        $request = $request->withHeader('Content-Type', 'application/json');
        if (($token ?? $this->token) !== '') {
            $request = $request->withHeader('Authorization', 'Bearer '.($token ?? $this->token));
        }
        $request->getBody()->write($body);

        return $this->app->handle($request);
    }

    /**
     * Checks exact safe no-store wire responses against attribute-owned OpenAPI
     */
    private function check(ResponseInterface $response, int $status, string $operation): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringNotContainsString('fixture-secret', (string) $response->getBody());
        if ($status === 200) {
            self::assertSame(['status' => 'success', 'data' => null], json_decode((string) $response->getBody(), true));
        }
        static $spec = null;
        $spec ??= Document::generate();
        (new ValidatorBuilder())->fromJson($spec)->getResponseValidator()->validate(
            new OperationAddress(
                '/api/v1/users/{userId}'.($operation === 'delete' ? '' : '/'.$operation),
                $operation === 'delete' ? 'delete' : 'post'
            ),
            $response
        );
    }
}
