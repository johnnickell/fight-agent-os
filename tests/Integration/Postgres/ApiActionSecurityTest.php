<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Action\Api\V1\Me\ReadCurrentPrincipalAction;
use App\Adapter\Http\Action\ClientShellAction;
use App\Adapter\Http\Responder\Api\V1\Me\ReadCurrentPrincipalResponder;
use App\Adapter\Http\Web\ClientAssetManifest;
use App\Adapter\Http\Web\ClientShellResponder;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Adapter\Persistence\Repository\PostgresPermissionRepository;
use App\Adapter\Persistence\Repository\PostgresRefreshSessionRepository;
use App\Adapter\Persistence\Repository\PostgresRoleRepository;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use App\Adapter\Security\AccessToken\SystemAccessClock;
use App\Domain\Authorization\InstallationManagedPolicy;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
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
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AccessClock;
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
    private bool $routesMounted = false;

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
        $this->sessions = new PostgresRefreshSessionRepository(
            $this->connection,
            new \App\Adapter\Persistence\Locking\AuthenticationAuthorityFences($this->connection)
        );
        $this->tokens = new AccessTokens();
        $this->tokens->clock->time = new DateTimeImmutable('2026-09-28T12:00:00Z');
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
        // Install deterministic dependencies before the real route table captures its middleware.
        $container = require dirname(__DIR__, 3).'/config/services.php';
        $container->set(SystemAccessClock::class, fn(): AccessClock => $this->tokens->clock);
        AppFactory::setContainer($container);
        AppFactory::setResponseFactory(new ResponseFactory());
        /** @var App<Container> $app */
        $app = AppFactory::create();
        $this->app = $app;
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
                $this->tokens->clock->now()
            );
            $replacement->advanceAuthenticationAuthorityRevision();
            self::assertTrue($this->connection->transactional(
                fn() => $this->users->replaceAuthenticationAuthority($this->user, $replacement)
            ));
        } elseif (in_array($case, ['idle expired', 'absolute expired', 'inactive'], true)) {
            if ($case === 'inactive') {
                $this->connection->executeStatement("UPDATE users SET state = 'disabled' WHERE id = ?", [
                    $this->user->getId()->toString()
                ]);
            } else {
                $deadline = $this->tokens->clock->now();
                $absolute = $case === 'absolute expired' ? $deadline : $this->session->getAbsoluteExpiresAt();
                // Persist an exact boundary without advancing JWT time past its independent expiry.
                $this->connection->executeStatement(
                    'UPDATE refresh_sessions SET idle_expires_at = ?, absolute_expires_at = ? WHERE id = ?',
                    [$deadline->format('Y-m-d H:i:s.uP'), $absolute->format('Y-m-d H:i:s.uP'),
                        $this->session->getId()->toString()]
                );
            }
        } elseif (in_array($case, ['wrong subject', 'wrong session', 'expired access', 'wrong owner'], true)) {
            $claims = $this->tokens->claims();
            $claims['sub'] = $this->user->getId()->toString();
            $claims['sid'] = $this->session->getId()->toString();
            if ($case === 'wrong subject') {
                $claims['sub'] = UserId::generate()->toString();
            } elseif ($case === 'wrong session') {
                $claims['sid'] = RefreshSessionId::generate()->toString();
            } elseif ($case === 'wrong owner') {
                $other = User::invite(
                    UserId::generate(),
                    EmailAddress::fromString('other@example.test'),
                    $this->tokens->clock->now()
                );
                $other->activate(
                    PasswordHash::fromString((string) password_hash('private-other', PASSWORD_BCRYPT)),
                    $this->tokens->clock->now()
                );
                $this->connection->transactional(fn() => $this->users->add($other));
                $claims['sub'] = $other->getId()->toString();
            } else {
                $claims['iat'] -= 900;
                $claims['nbf'] -= 900;
                $claims['exp'] -= 900;
            }
            $token = $this->tokens->signed(json_encode($claims, JSON_THROW_ON_ERROR));
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
        foreach (
            [
            'anonymous', 'invalid', 'revoked', 'stale', 'role claim', 'idle expired', 'absolute expired',
            'inactive', 'wrong subject', 'wrong session', 'expired access', 'wrong owner'
            ] as $case
        ) {
            yield $case => [$case];
        }
    }

    /**
     * Observes changed role permissions and session revocation across requests on the same application
     */
    public function testAuthorityFreshness(): void
    {
        $now = $this->tokens->clock->now();
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
     * Projects each managed membership through the real HTTP route without inferring new grants
     *
     * @param string $name
     * @param array<int, string> $expectedPermissions
     */
    #[DataProvider('managedMemberships')]
    public function testManagedMemberships(string $name, array $expectedPermissions): void
    {
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $container->get(SynchronousCommandBus::class)->execute(
            new ReconcileManagedPolicy(InstallationManagedPolicy::create())
        );
        $role = $this->roles->getByName(RoleName::fromString($name));
        self::assertNotNull($role);
        $replacement = clone $this->user;
        $replacement->assignRole($role->getId(), $this->tokens->clock->now());
        self::assertTrue($this->connection->transactional(
            fn() => $this->users->replaceRoleAssignments($this->user, $replacement)
        ));
        $response = $this->request($this->token);
        $this->assertResponse($response, 200);
        self::assertSame([
            'user_id'     => $this->user->getId()->toString(),
            'email'       => 'current@example.test',
            'roles'       => [$name],
            'permissions' => $expectedPermissions
        ], json_decode((string) $response->getBody(), true)['data']);
        self::assertSame([], $this->logs->getRecords());
    }

    /**
     * Supplies independently enumerated approved role grants, not catalog-derived expectations
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function managedMemberships(): iterable
    {
        yield 'ordinary' => ['ROLE_USER', ['DELETE_OWN_SESSIONS', 'READ_OWN_SESSIONS', 'VIEW_DASHBOARD']];
        yield 'super admin' => ['ROLE_SUPER_ADMIN', [
            'ASSIGN_SUPER_ADMIN', 'CREATE_ROLES', 'DELETE_OWN_SESSIONS', 'DELETE_ROLES', 'DELETE_SESSIONS',
            'DELETE_USERS', 'DISABLE_USERS', 'ENABLE_USERS', 'INVITE_USERS', 'MANAGE_ROLE_PERMISSIONS',
            'MANAGE_USER_INVITATIONS', 'MANAGE_USER_ROLES', 'READ_OWN_SESSIONS', 'READ_PERMISSIONS',
            'READ_ROLES', 'READ_SESSIONS', 'READ_SWAGGER', 'READ_USERS', 'REMOVE_SUPER_ADMIN', 'RESTORE_USERS',
            'UPDATE_ROLES', 'VIEW_DASHBOARD'
        ]];
    }

    /**
     * Rejects incomplete and duplicated repository authority without partial identity or diagnostic leaks
     */
    #[DataProvider('referenceFailures')]
    public function testReferenceFailures(string $failure): void
    {
        $now = $this->tokens->clock->now();
        $permission = Permission::define(PermissionId::generate(), PermissionName::fromString('VIEW_DASHBOARD'), $now);
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
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        if (str_contains($failure, 'role')) {
            $repository = $this->createStub(RoleRepository::class);
            $repository->method('getByIds')->willReturn($failure === 'missing role' ? [] : [$role, $role]);
            $repository->method('getByName')->willReturn(null);
            $container->set(RoleRepository::class, static fn(): RoleRepository => $repository);
        } else {
            $repository = $this->createStub(PermissionRepository::class);
            $repository->method('getByIds')->willReturn(
                $failure === 'missing permission' ? [] : [$permission, $permission]
            );
            $container->set(PermissionRepository::class, static fn(): PermissionRepository => $repository);
        }
        $response = $this->request($this->token);
        $this->assertResponse($response, 401);
        self::assertSame(
            ['status' => 'error', 'message' => 'Unauthorized.'],
            json_decode((string) $response->getBody(), true)
        );
        self::assertSame(0, $this->queries);
        self::assertSame([], $this->logs->getRecords());
    }

    /**
     * Supplies corrupt authoritative-reference responses behind the real middleware boundary
     *
     * @return iterable<string, array{string}>
     */
    public static function referenceFailures(): iterable
    {
        foreach (['missing role', 'duplicate role', 'missing permission', 'duplicate permission'] as $failure) {
            yield $failure => [$failure];
        }
    }

    /**
     * Resolves the package principal once across the middleware gate and Action, not once per consumer
     */
    public function testOneRequestSnapshot(): void
    {
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $roles = $this->createMock(RoleRepository::class);
        $roles->expects(self::once())->method('getByIds')->willReturn([]);
        $roles->expects(self::once())->method('getByName')->willReturn(null);
        $container->set(RoleRepository::class, static fn(): RoleRepository => $roles);
        $this->assertResponse($this->request($this->token), 200);
        self::assertSame(1, $this->queries);
    }

    /**
     * Preserves true misses and public root delivery without invoking protected API authority
     */
    #[DataProvider('rootDeployments')]
    public function testMissesAndRoot(bool $available, int $rootStatus): void
    {
        $cases = [
            ['POST', '/api/v1/me', 405],
            ['GET', '/api/v1/absent', 404],
            ['GET', '/mcp', 404]
        ];
        foreach ($cases as [$method, $path, $status]) {
            self::assertSame($status, $this->request(null, $method, $path)->getStatusCode());
        }
        $response = $this->requestRootWithAssets($available);
        $body = (string) $response->getBody();
        self::assertSame($rootStatus, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertStringContainsString("default-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertFalse($response->hasHeader('WWW-Authenticate'));
        self::assertFalse($response->hasHeader('Set-Cookie'));
        if ($available) {
            self::assertStringContainsString('id="app"', $body);
            self::assertStringContainsString('src="/build/main-AAAAAAAA.js"', $body);
        } else {
            self::assertStringContainsString('Application unavailable', $body);
            self::assertStringNotContainsString('<script', $body);
            self::assertStringNotContainsString('/build/', $body);
            self::assertStringNotContainsString('runtime-config', $body);
        }
        self::assertStringNotContainsString($this->token, $body);
        self::assertStringNotContainsString($this->user->getId()->toString(), $body);
        self::assertStringNotContainsString('permissions', $body);
        self::assertStringNotContainsString('roles', $body);
        self::assertSame(0, $this->constructions);
        self::assertSame(0, $this->queries);
    }

    /**
     * Supplies public shell deployment outcomes rather than authentication outcomes
     *
     * @return iterable<string, array{bool, int}>
     */
    public static function rootDeployments(): iterable
    {
        yield 'available shell' => [true, 200];
        yield 'unavailable shell' => [false, 503];
    }

    /**
     * Requests the real root with isolated deployment inputs, never the developer's published assets
     */
    private function requestRootWithAssets(bool $available): ResponseInterface
    {
        $directory = dirname(__DIR__, 3).'/.runs/build/tmp/test-api-root/'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        try {
            if ($available) {
                foreach (['prepaint-CCCCCCCC.js', 'main-AAAAAAAA.js', 'main-BBBBBBBB.css'] as $asset) {
                    file_put_contents($directory.'/'.$asset, '');
                }
                file_put_contents($directory.'/manifest.json', json_encode([
                    'prepaint'   => '/build/prepaint-CCCCCCCC.js',
                    'script'     => '/build/main-AAAAAAAA.js',
                    'stylesheet' => '/build/main-BBBBBBBB.css'
                ], JSON_THROW_ON_ERROR));
            }
            $container = $this->app->getContainer();
            self::assertInstanceOf(Container::class, $container);
            $container->set(ClientShellAction::class, static fn(Container $container) => new ClientShellAction(
                new ClientAssetManifest($directory),
                $container->get(ClientShellResponder::class)
            ));

            return $this->request(null, 'GET', '/');
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    /**
     * Sends only actual registered route requests
     */
    private function request(?string $token, string $method = 'GET', string $path = '/api/v1/me'): ResponseInterface
    {
        if (!$this->routesMounted) {
            $this->routesMounted = true;
            $app = $this->app;
            $container = $app->getContainer();
            require dirname(__DIR__, 3).'/config/routes.php';
            require dirname(__DIR__, 3).'/config/middleware.php';
        }
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
