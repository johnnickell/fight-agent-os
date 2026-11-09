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
use Fight\AccessControl\Domain\AccessControl\User\Command\DisableUser;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
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
        foreach (['DATABASE_URL', 'APP_ACCESS_TOKEN_CONFIG'] as $name) {
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
        foreach (['disable', 'enable'] as $operation) {
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
        string $body = '{"confirm":true}',
        ?string $token = null
    ): ResponseInterface {
        $path = '/api/v1/users/'.$targetId->toString().'/'.$operation;
        $request = new ServerRequestFactory()->createServerRequest(
            'POST',
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
            new OperationAddress('/api/v1/users/{userId}/'.$operation, 'post'),
            $response
        );
    }
}
