<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Action\Api\V1\Me\ReadCurrentPrincipalAction;
use App\Adapter\Http\Responder\Api\V1\Me\ReadCurrentPrincipalResponder;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Adapter\Persistence\Repository\PostgresPermissionRepository;
use App\Adapter\Persistence\Repository\PostgresRefreshSessionRepository;
use App\Adapter\Persistence\Repository\PostgresRoleRepository;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AccessTokens;
use Tooling\OpenApi\Document;

/**
 * Proves the real current-principal route against isolated authoritative PostgreSQL
 */
final class ApiActionSecurityTest extends TestCase
{
    private Connection $connection;
    private PostgresUserRepository $users;
    private PostgresRoleRepository $roles;
    private PostgresRefreshSessionRepository $sessions;
    private User $user;
    private RefreshSession $session;
    private AccessTokens $tokens;
    private string $token;
    /** @var App<Container> */
    private App $app;
    private int $constructions = 0;
    private int $queries = 0;
    private TestHandler $logs;
    private string $configuration;
    private string $databaseUrl;

    /**
     * Seeds package state and boots only the real route table on a guarded test connection
     */
    protected function setUp(): void
    {
        $this->configuration = (string) getenv('APP_ACCESS_TOKEN_CONFIG');
        $this->databaseUrl = (string) getenv('DATABASE_URL');
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection(new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ])->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement('TRUNCATE users, refresh_sessions, roles, permissions CASCADE');
        $fences = new AuthorizationReferenceFences($this->connection);
        $this->users = new PostgresUserRepository(
            $this->connection,
            $fences,
            new AuthenticationAuthorityFences($this->connection)
        );
        $this->roles = new PostgresRoleRepository($this->connection, $fences);
        $this->sessions = new PostgresRefreshSessionRepository($this->connection);
        $this->tokens = new AccessTokens();
        $this->tokens->clock->time = new DateTimeImmutable('now');
        $now = $this->tokens->clock->now()->modify('-1 minute');
        $this->user = User::invite(UserId::generate(), EmailAddress::fromString('current@example.test'), $now);
        $this->user->activate(
            PasswordHash::fromString((string) password_hash('private-password', PASSWORD_BCRYPT)),
            $now
        );
        $this->connection->transactional(fn() => $this->users->add($this->user));
        $this->session = RefreshSession::start(
            RefreshSessionId::generate(),
            $this->user->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            $this->user->getAuthenticationVersion(),
            false
        );
        $this->connection->transactional(fn() => RefreshSessionRecords::insert($this->connection, $this->session));
        $claims = $this->tokens->claims();
        $claims['sub'] = $this->user->getId()->toString();
        $claims['sid'] = $this->session->getId()->toString();
        $claims['auth_version'] = $this->user->getAuthenticationVersion();
        $this->token = $this->tokens->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        putenv('APP_ACCESS_TOKEN_CONFIG='.json_encode($this->tokens->configuration, JSON_THROW_ON_ERROR));
        putenv('DATABASE_URL='.$url);
        $this->app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $this->logs = new TestHandler();
        $container->get('http.failure.logger')->setHandlers([$this->logs]);
        $realQueries = $container->get(QueryBus::class);
        $spy = $this->createMock(QueryBus::class);
        $spy->method('fetch')->willReturnCallback(function ($query) use ($realQueries) {
            ++$this->queries;

            return $realQueries->fetch($query);
        });
        $container->set(QueryBus::class, static fn(): QueryBus => $spy);
        $container->set(ReadCurrentPrincipalAction::class, function () use (
            $spy,
            $container
        ): ReadCurrentPrincipalAction {
            ++$this->constructions;

            return new ReadCurrentPrincipalAction($spy, $container->get(ReadCurrentPrincipalResponder::class));
        });
    }

    /**
     * Restores private boot settings and releases the task test connection
     */
    protected function tearDown(): void
    {
        putenv('APP_ACCESS_TOKEN_CONFIG='.$this->configuration);
        putenv('DATABASE_URL='.$this->databaseUrl);
        $this->connection->close();
    }

    /**
     * Returns a minimal safe View with no dashboard permission and no session or credential material
     */
    public function testSafeCurrentPrincipal(): void
    {
        $response = $this->request($this->token);
        $this->assertResponse($response, 200);
        self::assertSame([
            'status' => 'success',
            'data'   => [
                'user_id'     => $this->user->getId()->toString(),
                'email'       => 'current@example.test',
                'roles'       => [],
                'permissions' => []
            ]
        ], json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR));
        self::assertSame(1, $this->constructions);
        self::assertSame(1, $this->queries);
        self::assertStringNotContainsString($this->session->getId()->toString(), (string) $response->getBody());
        self::assertStringNotContainsString($this->token, (string) $response->getBody());
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }

    /**
     * Rejects missing, invalid and stale authority before Action construction and package query work
     */
    #[DataProvider('refusals')]
    public function testRefusedBeforeConstruction(string $case): void
    {
        $token = $this->token;
        if ($case === 'anonymous') {
            $token = null;
        } elseif ($case === 'invalid') {
            $token .= 'x';
        } elseif ($case === 'revoked') {
            $this->connection->transactional(
                fn() => $this->sessions->replace($this->session, $this->session->revoke())
            );
        } elseif ($case === 'stale') {
            $replacement = clone $this->user;
            $replacement->changePassword(
                PasswordHash::fromString((string) password_hash('replacement', PASSWORD_BCRYPT)),
                new DateTimeImmutable()
            );
            $replacement->advanceAuthenticationAuthorityRevision();
            self::assertTrue($this->connection->transactional(
                fn() => $this->users->replaceAuthenticationAuthority($this->user, $replacement)
            ));
        } elseif ($case === 'role claim') {
            $claims = $this->tokens->claims();
            $claims['sub'] = $this->user->getId()->toString();
            $claims['sid'] = $this->session->getId()->toString();
            $claims['roles'] = ['ROLE_SUPER_ADMIN'];
            $token = $this->tokens->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        }
        $response = $this->request($token);
        $this->assertResponse($response, 401);
        self::assertSame(
            ['status' => 'error', 'message' => 'Unauthorized.'],
            json_decode((string) $response->getBody(), true)
        );
        self::assertSame(0, $this->constructions);
        self::assertSame(0, $this->queries);
        self::assertSame([], $this->logs->getRecords());
    }

    /**
     * Supplies real-route refusal states
     *
     * @return iterable<string, array{string}>
     */
    public static function refusals(): iterable
    {
        foreach (['anonymous', 'invalid', 'revoked', 'stale', 'role claim'] as $case) {
            yield $case => [$case];
        }
    }

    /**
     * Observes changed role permissions and session revocation across requests on the same application
     */
    public function testAuthorityFreshness(): void
    {
        $now = new DateTimeImmutable();
        $permission = Permission::define(PermissionId::generate(), PermissionName::fromString('READ_USERS'), $now);
        $permissions = new PostgresPermissionRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection)
        );
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_READER'), [$permission->getId()], $now);
        $replacement = clone $this->user;
        $replacement->assignRole($role->getId(), $now);
        $this->connection->transactional(function () use ($permissions, $permission, $role, $replacement): void {
            $permissions->add($permission);
            $this->roles->add($role);
            $this->users->replaceRoleAssignments($this->user, $replacement);
        });
        $first = $this->request($this->token);
        $this->assertResponse($first, 200);
        self::assertSame(['READ_USERS'], json_decode((string) $first->getBody(), true)['data']['permissions']);
        self::assertSame(['ROLE_READER'], json_decode((string) $first->getBody(), true)['data']['roles']);
        $changed = $role->revokePermissionFromCustom($permission->getId(), $now);
        $this->connection->transactional(fn() => $this->roles->replace($role, $changed));
        $second = $this->request($this->token);
        $this->assertResponse($second, 200);
        self::assertSame([], json_decode((string) $second->getBody(), true)['data']['permissions']);
        self::assertSame(2, $this->queries);
        $this->connection->transactional(fn() => $this->sessions->replace($this->session, $this->session->revoke()));
        $this->assertResponse($this->request($this->token), 401);
        self::assertSame(2, $this->queries);
        self::assertSame(1, $this->constructions);
    }

    /**
     * Preserves true method and route misses instead of manufacturing authentication outcomes
     */
    public function testMissesAndRoot(): void
    {
        $cases = [
            ['POST', '/api/v1/me', 405],
            ['GET', '/api/v1/absent', 404],
            ['GET', '/mcp', 404],
            ['GET', '/', 200]
        ];
        foreach ($cases as [$method, $path, $status]) {
            self::assertSame($status, $this->request(null, $method, $path)->getStatusCode());
        }
        self::assertSame(0, $this->constructions);
        self::assertSame(0, $this->queries);
    }

    /**
     * Sends only actual registered route requests
     */
    private function request(?string $token, string $method = 'GET', string $path = '/api/v1/me'): ResponseInterface
    {
        $request = new ServerRequestFactory()->createServerRequest($method, 'https://agent-os.test'.$path);
        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer '.$token);
        }

        return $this->app->handle($request);
    }

    /**
     * Checks the actual private OpenAPI response and no-store correlation contract
     */
    private function assertResponse(ResponseInterface $response, int $status): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $response->getHeaderLine('X-Correlation-ID'));
        (new ValidatorBuilder())->fromJson(Document::generate())->getResponseValidator()->validate(
            new OperationAddress('/api/v1/me', 'get'),
            $response
        );
    }
}
