<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Api\Auth\RequestAuthenticationContext;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Adapter\Security\AccessToken\AccessJwt;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use App\Application\Sessions\RevokeOwnedSession;
use App\Domain\Authorization\InstallationManagedPolicy;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionAdministrationAuthorization;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Command\RevokeSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Event\RefreshSessionRevoked;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\SessionRevocationReason;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Utility\ClassName;
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
 * Exercises other-owned revocation through actual HTTP, package dispatch and PostgreSQL authority
 */
final class OwnedSessionRevocationTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private User $actor;
    private RefreshSession $current;
    private AccessTokens $tokens;
    private int $events = 0;
    /** @var array<string, string> */
    private array $credentials = [];
    /** @var array<string, string|false> */
    private array $environment = [];

    /**
     * Boots the guarded task database and coherent deterministic authentication clocks
     */
    protected function setUp(): void
    {
        foreach (['DATABASE_URL', 'APP_ACCESS_TOKEN_CONFIG'] as $name) {
            $this->environment[$name] = getenv($name);
        }
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement('TRUNCATE users, roles, permissions, audit_evidence CASCADE');
        $this->tokens = new AccessTokens();
        $this->tokens->clock->time = new DateTimeImmutable('2026-10-25T12:00:00Z');
        putenv('DATABASE_URL='.$url);
        putenv('APP_ACCESS_TOKEN_CONFIG='.json_encode($this->tokens->configuration, JSON_THROW_ON_ERROR));
        $container = require dirname(__DIR__, 3).'/config/services.php';
        self::assertInstanceOf(Container::class, $container);
        $this->container = $container;
        $container->set(Clock::class, fn(): Clock => $this->tokens->clock);
        $container->set(AccessJwt::class, fn(): AccessJwt => $this->tokens->jwt);
        $container->set(AuthenticateAccessToken::class, fn(Container $c) => new AuthenticateAccessToken(
            $this->tokens->jwt,
            $c->get(UserRepository::class),
            $c->get(RefreshSessionRepository::class),
            $this->tokens->clock
        ));
        $container->set(AuthoritativePrincipalResolver::class, fn(Container $c) => new AuthoritativePrincipalResolver(
            $c->get(UserRepository::class),
            $c->get(RefreshSessionRepository::class),
            $c->get(RoleRepository::class),
            $c->get(PermissionRepository::class),
            $this->tokens->clock
        ));
        AppFactory::setContainer($container);
        AppFactory::setResponseFactory(new ResponseFactory());
        /** @var App<Container> $app */
        $app = AppFactory::create();
        $this->app = $app;
        $container->set(App::class, static fn(): App => $app);
        require dirname(__DIR__, 3).'/config/routes.php';
        require dirname(__DIR__, 3).'/config/middleware.php';
        $container->get(CommandBus::class)->execute(new ReconcileManagedPolicy(
            $container->get(\Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy::class)
        ));
        $this->actor = $this->user('owner@example.test');
        $this->current = $this->session($this->actor);
        $container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(RefreshSessionRevoked::class),
            function (): void {
            // The event must follow the actual committed state, visible from an independent connection.
                self::assertGreaterThan(0, (int) $this->connection->fetchOne(
                    'SELECT count(*) FROM refresh_sessions WHERE revoked = TRUE'
                ));
                ++$this->events;
            }
        );
    }

    /**
     * Restores environment without touching other stacks
     */
    protected function tearDown(): void
    {
        $this->connection->executeStatement('ALTER TABLE refresh_sessions DROP CONSTRAINT IF EXISTS revoke_abort');
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        $this->connection->close();
    }

    /**
     * Proves target-only mutation, immediate access rejection and safe repeated outcome
     */
    public function testCommitAndImmediateTokenRejection(): void
    {
        $target = $this->session($this->actor);
        $foreign = $this->session($this->user('other@example.test'));
        $before = $this->connection->fetchAllAssociative('SELECT * FROM refresh_sessions ORDER BY id');
        $response = $this->request($target->getId()->toString());
        $this->check($response, 200);
        self::assertSame(['status' => 'success', 'data' => null], $this->body($response));
        $after = $this->connection->fetchAllAssociative('SELECT * FROM refresh_sessions ORDER BY id');
        foreach ($before as $i => $row) {
            if ($row['id'] === $target->getId()->toString()) {
                $row['revoked'] = true;
                ++$row['revision'];
            }
            self::assertSame($row, $after[$i]);
        }
        self::assertSame(1, $this->events);
        foreach ([$this->current, $target, $foreign] as $session) {
            $request = new ServerRequestFactory()->createServerRequest('GET', 'https://agent-os.test/api/v1/me')
                ->withHeader('Authorization', 'Bearer '.$this->token($session));
            $result = $this->app->handle($request);
            self::assertSame($session === $target ? 401 : 200, $result->getStatusCode());
            self::assertStringNotContainsString($session->getCredentialDigest(), (string) $result->getBody());
        }
        $list = new ServerRequestFactory()->createServerRequest('GET', 'https://agent-os.test/api/v1/me/sessions')
            ->withHeader('Authorization', 'Bearer '.$this->token($this->current));
        $records = $this->body($this->app->handle($list))['data']['records'];
        self::assertSame([$this->current->getId()->toString()], array_column($records, 'session_id'));
        $this->check($this->request($target->getId()->toString()), 409);
        self::assertSame(1, $this->events);
    }

    /**
     * Rejects target refresh immediately while preserving current refresh authority
     */
    public function testImmediateRefreshRejection(): void
    {
        $target = $this->session($this->actor);
        $this->check($this->request($target->getId()->toString()), 200);
        $authentication = $this->container->get(AuthenticationService::class);
        $authentication->refresh($this->credentials[$this->current->getId()->toString()]);
        $this->expectException(RefreshSessionNotFoundException::class);
        $authentication->refresh($this->credentials[$target->getId()->toString()]);
    }

    /**
     * Rolls back failed storage atomically and never reclassifies it as a successful or safe target outcome
     */
    public function testStorageFailureRedactionAndRollback(): void
    {
        $target = $this->session($this->actor);
        $before = $this->connection->fetchAllAssociative('SELECT * FROM refresh_sessions ORDER BY id');
        $this->connection->executeStatement(
            'ALTER TABLE refresh_sessions ADD CONSTRAINT revoke_abort CHECK (revoked = FALSE) NOT VALID'
        );
        $response = $this->request($target->getId()->toString());
        $this->check($response, 500);
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], $this->body($response));
        self::assertSame(
            $before,
            $this->connection->fetchAllAssociative('SELECT * FROM refresh_sessions ORDER BY id')
        );
        self::assertSame(0, $this->events);
        $privateValues = [$target->getId()->toString(), $target->getCredentialDigest(), 'revoke_abort', 'SQLSTATE'];
        foreach ($privateValues as $private) {
            self::assertStringNotContainsString($private, (string) $response->getBody());
        }
    }

    /**
     * Collapses cross-user, nonexistent, expired and already revoked outcomes byte-for-byte
     */
    public function testNonEnumeratingFailuresAndCurrentInstruction(): void
    {
        $foreign = $this->session($this->user('other@example.test'));
        $idle = $this->session($this->actor);
        $absolute = $this->session($this->actor);
        $revoked = $this->session($this->actor);
        $at = RefreshSessionRecords::date($this->tokens->clock->now());
        $this->connection->executeStatement('UPDATE refresh_sessions SET idle_expires_at = ? WHERE id = ?', [
            $at, $idle->getId()->toString()
        ]);
        $this->connection->executeStatement(
            'UPDATE refresh_sessions SET idle_expires_at = ?, absolute_expires_at = ? WHERE id = ?',
            [$at, $at, $absolute->getId()->toString()]
        );
        $this->connection->executeStatement('UPDATE refresh_sessions SET revoked = TRUE WHERE id = ?', [
            $revoked->getId()->toString()
        ]);
        $ids = [$foreign->getId(), $idle->getId(), $absolute->getId(), $revoked->getId(), RefreshSessionId::generate()];
        foreach ($ids as $id) {
            $response = $this->request($id->toString());
            $this->check($response, 409);
            self::assertSame(['status' => 'fail', 'data' => ['message' => 'State conflict.']], $this->body($response));
            self::assertStringNotContainsString($id->toString(), (string) $response->getBody());
        }
        $response = $this->request($this->current->getId()->toString());
        $this->check($response, 409);
        self::assertSame(
            ['status' => 'fail', 'data' => [
                'message' => 'Use Logout for the current session.', 'intent' => 'use_logout'
            ]],
            $this->body($response)
        );
        self::assertFalse($this->connection->fetchOne('SELECT revoked FROM refresh_sessions WHERE id = ?', [
            $this->current->getId()->toString()
        ]));
        self::assertSame(0, $this->events);
    }

    /**
     * Requires exact authoritative self-service permission at HTTP and non-HTTP entrypoints
     */
    public function testPermissionAndPrincipalOnlyCommand(): void
    {
        $target = $this->session($this->actor);
        $this->check($this->request($target->getId()->toString(), authenticated: false), 401);
        $this->connection->executeStatement(<<<'SQL'
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = 'DELETE_OWN_SESSIONS')
SQL);
        $this->check($this->request($target->getId()->toString()), 403);
        $this->expectException(PermissionDenied::class);
        $this->container->get(RevokeOwnedSession::class)->revoke($this->provider(), $target->getId());
    }

    /**
     * Qualifies the released package's reasoned audit in the real shared transaction
     */
    public function testPackageAdministrativeAudit(): void
    {
        $target = $this->session($this->user('other@example.test'));
        $this->container->get(CommandBus::class)->execute(new RevokeSession(
            $this->actor->getId(),
            $this->current->getId(),
            $target->getId(),
            SessionRevocationReason::fromString('Explicit test reason')
        ));
        self::assertTrue($this->connection->fetchOne('SELECT revoked FROM refresh_sessions WHERE id = ?', [
            $target->getId()->toString()
        ]));
        $audit = $this->connection->fetchAssociative(
            "SELECT * FROM audit_evidence WHERE action = 'refresh_session.administratively_revoked'"
        );
        self::assertIsArray($audit);
        self::assertSame($this->actor->getId()->toString(), $audit['actor_id']);
        self::assertSame($target->getUserId()->toString(), $audit['subject_id']);
        self::assertNotEmpty($audit['recorded_at']);
        self::assertStringNotContainsString($target->getCredentialDigest(), $audit['context']);
        self::assertSame([
            'reason' => 'Explicit test reason', 'refresh_session_id' => $target->getId()->toString()
        ], json_decode($audit['context'], true));
    }

    /**
     * Rejects malformed identities and every body/query override without a mutation
     */
    #[DataProvider('invalidInputs')]
    public function testInputBoundaries(string $target, string $body, string $query): void
    {
        $id = $this->session($this->actor)->getId()->toString();
        $response = $this->request($target === 'valid' ? $id : $target, $body, $query);
        $this->check($response, 400);
        self::assertSame(0, $this->events);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions WHERE revoked'));
        self::assertStringNotContainsString('private-marker', (string) $response->getBody());
    }

    /**
     * Supplies transport ambiguities and forbidden authority selectors
     *
     * @return iterable<array{string, string, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield ['invalid-private-marker', '{}', ''];
        yield ['valid', '[]', ''];
        yield ['valid', '', ''];
        yield ['valid', '{"actor_id":"private-marker"}', ''];
        yield ['valid', '{"current_session_id":"private-marker"}', ''];
        yield ['valid', '{"reason":"private-marker"}', ''];
        yield ['valid', '{"user_id":"private-marker","permissions":["DELETE_SESSIONS"]}', ''];
        yield ['valid', '{}', '?actor_id=private-marker'];
    }

    /**
     * Proves competing revoke, rotation, logout and expiry cannot be overwritten from an older snapshot
     */
    #[DataProvider('raceWinners')]
    public function testSnapshotRaces(string $winner, bool $administrative): void
    {
        $target = $this->session($administrative ? $this->user('other@example.test') : $this->actor);
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', 'require "vendor/autoload.php"; Tests\\Support\\SessionRevocationRaceWorker::run();'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3)
        );
        self::assertIsResource($process);
        try {
            stream_set_timeout($pipes[1], 15);
            fwrite($pipes[0], json_encode([
                'actor'          => $this->actor->getId()->toString(),
                'current'        => $this->current->getId()->toString(),
                'target'         => $target->getId()->toString(),
                'time'           => $this->tokens->clock->now()->format('c'),
                'administrative' => $administrative
            ], JSON_THROW_ON_ERROR)."\n");
            fflush($pipes[0]);
            self::assertSame('ready', trim((string) fgets($pipes[1])));
            $authentication = $this->container->get(AuthenticationService::class);
            if ($winner === 'revoke') {
                if ($administrative) {
                    self::assertSame(200, $this->adminRequest($target)->getStatusCode());
                } else {
                    $this->check($this->request($target->getId()->toString()), 200);
                }
            } elseif ($winner === 'refresh') {
                $authentication->refresh($this->credentials[$target->getId()->toString()]);
            } elseif ($winner === 'logout') {
                $authentication->logout($this->credentials[$target->getId()->toString()]);
            } else {
                $this->connection->executeStatement('UPDATE refresh_sessions SET idle_expires_at = ? WHERE id = ?', [
                    RefreshSessionRecords::date($this->tokens->clock->now()), $target->getId()->toString()
                ]);
            }
            $before = $this->connection->fetchAssociative(
                'SELECT * FROM refresh_sessions WHERE id = ?',
                [$target->getId()->toString()]
            );
            fwrite($pipes[0], "go\n");
            fflush($pipes[0]);
            self::assertSame(['status' => 409, 'events' => 0], json_decode((string) fgets($pipes[1]), true));
            self::assertSame($before, $this->connection->fetchAssociative(
                'SELECT * FROM refresh_sessions WHERE id = ?',
                [$target->getId()->toString()]
            ));
            self::assertSame($winner === 'revoke' ? 1 : 0, $this->events);
            self::assertFalse($this->connection->fetchOne(
                'SELECT revoked FROM refresh_sessions WHERE id = ?',
                [$this->current->getId()->toString()]
            ));
            // A fresh decision can revoke the rotated winner; no stale snapshot can do so.
            $status = $winner === 'refresh' ? 200 : 409;
            if ($administrative) {
                self::assertSame($status, $this->adminRequest($target)->getStatusCode());
            } else {
                $this->check($this->request($target->getId()->toString()), $status);
            }
            self::assertSame(
                $administrative && in_array($winner, ['refresh', 'revoke'], true) ? 1 : 0,
                (int) $this->connection->fetchOne(
                    "SELECT count(*) FROM audit_evidence WHERE action = 'refresh_session.administratively_revoked'"
                )
            );
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
     * Supplies committed concurrent lifecycle winners
     *
     * @return iterable<array{string, bool}>
     */
    public static function raceWinners(): iterable
    {
        foreach (['revoke', 'refresh', 'logout', 'expiry'] as $winner) {
            yield [$winner, false];
            yield [$winner, true];
        }
    }

    /**
     * Exercises exact administrative permissions, scoped pages and committed target-only invalidation
     */
    public function testAdministrativeHttpAndImmediateAuthority(): void
    {
        $target = $this->session($this->user('other@example.test'));
        $other = $this->session($this->user('unrelated@example.test'));
        $response = $this->adminRequest($target, 'GET');
        $this->checkAdmin($response, 200, 'get');
        self::assertSame(
            [$target->getId()->toString()],
            array_column($this->body($response)['data']['records'], 'session_id')
        );
        $privateValues = [
            $target->getCredentialDigest(), $other->getId()->toString(), $target->getUserId()->toString()
        ];
        foreach ($privateValues as $private) {
            self::assertStringNotContainsString($private, (string) $response->getBody());
        }
        $this->removePermission('READ_SESSIONS');
        $this->checkAdmin($this->adminRequest($target, 'GET'), 403, 'get');
        // DELETE does not accidentally require READ; old signed claims cannot retain removed reading authority.
        $this->checkAdmin($this->adminRequest($target), 200, 'delete');
        self::assertSame(1, $this->events);
        $this->checkAdmin($this->adminRequest($target), 409, 'delete');
        foreach ([$target, $other, $this->current] as $session) {
            $request = new ServerRequestFactory()->createServerRequest('GET', 'https://agent-os.test/api/v1/me')
                ->withHeader('Authorization', 'Bearer '.$this->token($session));
            self::assertSame($session === $target ? 401 : 200, $this->app->handle($request)->getStatusCode());
        }
        self::assertSame(1, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM audit_evidence WHERE action = 'refresh_session.administratively_revoked'"
        ));
        $this->expectException(RefreshSessionNotFoundException::class);
        $this->container->get(AuthenticationService::class)->refresh($this->credentials[$target->getId()->toString()]);
    }

    /**
     * Keeps reading independent of deletion and self-service permission without trusting role labels
     */
    public function testAdministrativeReadOnlyAndAnonymous(): void
    {
        $user = $this->user('other@example.test');
        $target = $this->session($user);
        $second = $this->session($user);
        $ids = [$target->getId()->toString(), $second->getId()->toString()];
        sort($ids);
        $page = $this->adminRequest($target, 'GET', query: '?page=2&per_page=1');
        $this->checkAdmin($page, 200, 'get');
        self::assertSame([$ids[1]], array_column($this->body($page)['data']['records'], 'session_id'));
        $this->checkAdmin($this->adminRequest($target, 'GET', query: '?per_page=101'), 400, 'get');
        $this->checkAdmin($this->adminRequest($target, 'GET', query: '?actor_id=private-marker'), 400, 'get');
        $this->removePermission('DELETE_SESSIONS');
        $this->checkAdmin($this->adminRequest($target, 'GET'), 200, 'get');
        $this->checkAdmin($this->adminRequest($target), 403, 'delete');
        $request = new ServerRequestFactory()->createServerRequest(
            'GET',
            'https://agent-os.test/api/v1/users/'.$target->getUserId()->toString().'/sessions'
        );
        $this->checkAdmin($this->app->handle($request), 401, 'get');
        $this->removePermission('READ_SESSIONS');
        $this->checkAdmin($this->adminRequest($target, 'GET'), 403, 'get');
        self::assertSame(0, $this->events);
    }

    /**
     * Rejects caller overrides and unsafe reasons before any mutation or evidence leakage
     */
    #[DataProvider('administrativeInputs')]
    public function testAdministrativeInput(string $body): void
    {
        $target = $this->session($this->user('other@example.test'));
        $response = $this->adminRequest($target, body: $body);
        // The existing credential-transport boundary rejects Bearer material before input validation.
        $this->checkAdmin($response, str_contains($body, 'Bearer ') ? 401 : 400, 'delete');
        self::assertStringNotContainsString('private-marker', (string) $response->getBody());
        self::assertSame(0, $this->events);
        self::assertFalse($this->connection->fetchOne('SELECT revoked FROM refresh_sessions WHERE id = ?', [
            $target->getId()->toString()
        ]));
    }

    /**
     * Supplies missing, excessive, malicious and caller-selected authority input
     *
     * @return iterable<array{string}>
     */
    public static function administrativeInputs(): iterable
    {
        foreach (
            ['{}', '[]', '{"reason":""}', '{"reason":"   "}', '{"reason":"Bearer private-marker"}',
            '{"reason":"https://private-marker.test"}', '{"reason":"Investigation\\nprivate-marker"}',
            '{"reason":"Investigation","actor_id":"private-marker"}',
            '{"reason":"Investigation","current_session_id":"private-marker"}',
            json_encode(['reason' => str_repeat('x', 501)], JSON_THROW_ON_ERROR),
            json_encode(['reason' => "\u{00a0}"], JSON_THROW_ON_ERROR)] as $body
        ) {
            yield [$body];
        }
    }

    /**
     * Collapses wrong-owner, missing, expired and revoked sessions without broadening own endpoints
     */
    public function testAdministrativeTargetBoundaries(): void
    {
        $target = $this->session($this->user('other@example.test'));
        $wrong = $this->user('wrong@example.test');
        $missing = $this->session($wrong);
        $this->connection->executeStatement('DELETE FROM refresh_sessions WHERE id = ?', [
            $missing->getId()->toString()
        ]);
        $this->checkAdmin($this->adminRequest($missing), 409, 'delete');
        $this->checkAdmin($this->adminRequest($target, user: $wrong->getId()), 409, 'delete');
        $this->checkAdmin($this->adminRequest($target, user: UserId::generate()), 409, 'delete');
        $this->checkAdmin($this->adminRequest($target, user: UserId::generate(), method: 'GET'), 403, 'get');
        $this->checkAdmin($this->adminRequest($this->current, 'GET'), 403, 'get');
        $this->checkAdmin($this->adminRequest($this->current), 403, 'delete');
        // Even a fully authorized administrator cannot supply the required reason through a self endpoint.
        $this->check($this->request($target->getId()->toString()), 409);
        $this->connection->executeStatement('UPDATE refresh_sessions SET idle_expires_at = ? WHERE id = ?', [
            RefreshSessionRecords::date($this->tokens->clock->now()), $target->getId()->toString()
        ]);
        $this->checkAdmin($this->adminRequest($target), 409, 'delete');
        self::assertSame([], $this->body($this->adminRequest($target, 'GET'))['data']['records']);
        self::assertSame(0, $this->events);
    }

    /**
     * Retains the package actor/target decision independently of successful HTTP permission admission
     */
    public function testPackageCallbackDenial(): void
    {
        $target = $this->session($this->user('other@example.test'));
        $decision = new class (
            $this->actor->getId(),
            $target->getUserId()
        ) implements SessionAdministrationAuthorization {
            public int $calls = 0;

            /**
             * Constructs the target-scoped denial probe
             */
            public function __construct(private readonly UserId $actor, private readonly UserId $target)
            {
            }

            /**
             * @inheritDoc
             */
            public function assertCanManageSessions(UserId $actorId, UserId $userId): void
            {
                TestCase::assertTrue($this->actor->equals($actorId));
                TestCase::assertTrue($this->target->equals($userId));
                ++$this->calls;
                throw new SessionAdministrationAuthorizationException();
            }
        };
        $this->container->set('sessions.authorization.read', static fn() => $decision);
        $this->container->set('sessions.authorization.revoke', static fn() => $decision);
        $this->checkAdmin($this->adminRequest($target, 'GET'), 403, 'get');
        $this->checkAdmin($this->adminRequest($target), 409, 'delete');
        self::assertSame(2, $decision->calls);
        self::assertSame(0, $this->events);
        self::assertFalse($this->connection->fetchOne('SELECT revoked FROM refresh_sessions WHERE id = ?', [
            $target->getId()->toString()
        ]));
    }

    /**
     * Rolls back session replacement when the required administrative audit cannot persist
     */
    public function testAdministrativeAuditFailureRollsBack(): void
    {
        $target = $this->session($this->user('other@example.test'));
        $this->connection->executeStatement(<<<'SQL'
ALTER TABLE audit_evidence ADD CONSTRAINT admin_audit_abort
CHECK (action <> 'refresh_session.administratively_revoked') NOT VALID
SQL);
        try {
            $response = $this->adminRequest($target);
            $this->checkAdmin($response, 500, 'delete');
            self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], $this->body($response));
            self::assertFalse($this->connection->fetchOne('SELECT revoked FROM refresh_sessions WHERE id = ?', [
                $target->getId()->toString()
            ]));
            self::assertSame(0, $this->events);
            self::assertStringNotContainsString('admin_audit_abort', (string) $response->getBody());
        } finally {
            $this->connection->executeStatement('ALTER TABLE audit_evidence DROP CONSTRAINT admin_audit_abort');
        }
    }

    /**
     * Removes one real grant so the next request must observe fresh authority
     */
    private function removePermission(string $name): void
    {
        $this->connection->executeStatement(
            'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = ?)',
            [$name]
        );
    }

    /**
     * Invokes the explicit target API with only safe reason input
     */
    private function adminRequest(
        RefreshSession $target,
        string $method = 'DELETE',
        string $body = '{"reason":"Security investigation"}',
        ?UserId $user = null,
        string $query = ''
    ): ResponseInterface {
        $path = '/api/v1/users/'.($user ?? $target->getUserId())->toString().'/sessions';
        if ($method === 'DELETE') {
            $path .= '/'.$target->getId()->toString();
        }
        $request = new ServerRequestFactory()->createServerRequest($method, 'https://agent-os.test'.$path.$query)
            ->withHeader('Authorization', 'Bearer '.$this->token($this->current));
        if ($method === 'DELETE') {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write($body);
        }

        return $this->app->handle($request);
    }

    /**
     * Checks administrative OpenAPI and no-store on success and failure
     */
    private function checkAdmin(ResponseInterface $response, int $status, string $method): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        (new ValidatorBuilder())->fromJson(Document::generate())->getResponseValidator()->validate(
            new OperationAddress(
                '/api/v1/users/{userId}/sessions'.($method === 'delete' ? '/{sessionId}' : ''),
                $method
            ),
            $response
        );
    }

    /**
     * Checks actual OpenAPI responses and no-store without exposing transport details
     */
    private function check(ResponseInterface $response, int $status): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        (new ValidatorBuilder())->fromJson(Document::generate())->getResponseValidator()->validate(
            new OperationAddress('/api/v1/me/sessions/{sessionId}', 'delete'),
            $response
        );
    }

    /**
     * Decodes only test-local public output
     *
     * @return array<string, mixed>
     */
    private function body(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Creates one active permission-bearing fixture identity
     */
    private function user(string $email): User
    {
        $now = $this->tokens->clock->now()->modify('-2 hours');
        $user = User::invite(UserId::generate(), EmailAddress::fromString($email), $now);
        $user->activate(
            PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_BCRYPT, ['cost' => 4])),
            $now
        );
        $user->assignRole(RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID), $now);
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(UserRepository::class)->add($user)
        );

        return $user;
    }

    /**
     * Persists one genuine package session
     */
    private function session(User $user): RefreshSession
    {
        $at = $this->tokens->clock->now()->modify('-1 hour');
        $credential = bin2hex(random_bytes(32));
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            RefreshCredential::fromString($credential),
            $at,
            $at->modify('+1 day'),
            $at->modify('+2 days'),
            $user->getAuthenticationVersion(),
            false
        );
        $this->connection->transactional(fn() => RefreshSessionRecords::insert($this->connection, $session));
        $this->credentials[$session->getId()->toString()] = $credential;

        return $session;
    }

    /**
     * Issues a validly signed access token without trusting it as session authority
     */
    private function token(RefreshSession $session): string
    {
        $claims = $this->tokens->claims();
        $claims['sub'] = $session->getUserId()->toString();
        $claims['sid'] = $session->getId()->toString();
        $claims['auth_version'] = $session->getAuthenticationVersion();

        return $this->tokens->signed(json_encode($claims, JSON_THROW_ON_ERROR));
    }

    /**
     * Resolves the non-HTTP principal against actual persisted authority
     */
    private function provider(): CurrentPrincipalProvider
    {
        return new CurrentPrincipalProvider(
            new RequestAuthenticationContext(new AuthenticationContext(
                $this->actor->getId(),
                $this->current->getId(),
                $this->actor->getAuthenticationVersion()
            )),
            $this->container->get(AuthoritativePrincipalResolver::class)
        );
    }

    /**
     * Invokes actual routing with no cookie-selected or body-selected identity
     */
    private function request(
        string $id,
        string $body = '{}',
        string $query = '',
        bool $authenticated = true
    ): ResponseInterface {
        $request = new ServerRequestFactory()->createServerRequest(
            'DELETE',
            'https://agent-os.test/api/v1/me/sessions/'.$id.$query
        )
            ->withHeader('Content-Type', 'application/json');
        if ($authenticated) {
            $request = $request->withHeader('Authorization', 'Bearer '.$this->token($this->current));
        }
        $request->getBody()->write($body);

        return $this->app->handle($request);
    }
}
