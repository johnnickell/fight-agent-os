<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Action\Api\V1\Me\ReadCurrentPrincipalAction;
use App\Adapter\Http\Action\ClientShellAction;
use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
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
use App\Adapter\Security\HmacCsrfProofs;
use App\Application\Security\Csrf\Clock\CsrfClock;
use App\Domain\Authorization\InstallationManagedPolicy;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
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
    use \Tests\Support\SecretObservability;

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
            ['status' => 'fail', 'data' => ['message' => 'Authentication required.']],
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
            new ReconcileManagedPolicy($container->get(
                \Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy::class
            ))
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
            'DELETE_USERS', 'DISABLE_USERS', 'DOWNLOAD_LOG_ARCHIVES', 'ENABLE_USERS', 'EXPORT_LOGS',
            'GENERATE_REFERENCE_IDS', 'INVITE_USERS',
            'MANAGE_ROLE_PERMISSIONS', 'MANAGE_USER_INVITATIONS', 'MANAGE_USER_ROLES', 'READ_LOGS', 'READ_OWN_SESSIONS',
            'READ_PERMISSIONS', 'READ_PROJECTS', 'READ_ROLES', 'READ_SESSIONS', 'READ_SWAGGER', 'READ_USERS',
            'REGISTER_PROJECTS', 'REMOVE_SUPER_ADMIN', 'RESTORE_LOG_ARCHIVES', 'RESTORE_USERS',
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
            ['status' => 'fail', 'data' => ['message' => 'Authentication required.']],
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
     * Refuses invalid authority across every protected operation before query dispatch or durable mutation
     */
    #[DataProvider('integratedAuthorityStates')]
    public function testIntegratedAuthenticationDenials(string $state): void
    {
        $this->reconcileMatrixPolicy();
        $this->assignMatrixRole('ROLE_SUPER_ADMIN');
        $token = $this->token;
        if ($state === 'anonymous' || $state === 'cookie only') {
            $token = null;
        } elseif ($state === 'invalid signature') {
            $token .= 'x';
        } elseif ($state === 'revoked') {
            $this->connection->executeStatement('UPDATE refresh_sessions SET revoked = TRUE');
        } elseif ($state === 'stale') {
            $this->connection->executeStatement('UPDATE users SET authentication_version = authentication_version + 1');
        } elseif (in_array($state, ['pending', 'disabled', 'deleted'], true)) {
            if ($state === 'pending') {
                $this->connection->executeStatement(
                    "UPDATE users SET state = 'pending_activation', password_hash = NULL"
                );
            } else {
                $this->connection->executeStatement('UPDATE users SET state = ?', [$state]);
            }
        } elseif (in_array($state, ['idle expired', 'absolute expired'], true)) {
            $assignments = 'idle_expires_at = ?';
            if ($state === 'absolute expired') {
                $assignments .= ', absolute_expires_at = ?';
            }
            $deadline = $this->tokens->clock->now()->format('Y-m-d H:i:s.uP');
            $this->connection->executeStatement(
                'UPDATE refresh_sessions SET '.$assignments,
                $state === 'idle expired' ? [$deadline] : [$deadline, $deadline]
            );
        } elseif ($state === 'missing role') {
            $repository = $this->createStub(RoleRepository::class);
            $repository->method('getByIds')->willReturn([]);
            $repository->method('getByName')->willReturn(null);
            $container = $this->app->getContainer();
            self::assertInstanceOf(Container::class, $container);
            $container->set(RoleRepository::class, static fn(): RoleRepository => $repository);
        } else {
            $claims = $this->tokens->claims();
            $claims['sub'] = $this->user->getId()->toString();
            $claims['sid'] = $this->session->getId()->toString();
            $claims['auth_version'] = $this->user->getAuthenticationVersion();
            if ($state === 'wrong owner') {
                $claims['sub'] = UserId::generate()->toString();
            } elseif ($state === 'missing session') {
                $claims['sid'] = RefreshSessionId::generate()->toString();
            } elseif ($state === 'authority snapshot') {
                $claims['permissions'] = ['READ_USERS'];
                $claims['roles'] = ['ROLE_SUPER_ADMIN'];
            }
            $token = $this->tokens->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        }
        $before = $this->matrixBusinessSnapshot();
        foreach (self::protectedOperations() as [$method, $path]) {
            $response = $this->request($token, $method, $this->matrixPath($path), $state === 'cookie only');
            $this->assertMatrixDenial($response, 401);
        }
        self::assertSame($before, $this->matrixBusinessSnapshot());
        self::assertSame(0, $this->queries);
        self::assertSame(0, $this->constructions);
        self::assertSame([], $this->logs->getRecords());
    }

    /**
     * Supplies independent invalid authentication and current-authority states
     *
     * @return iterable<string, array{string}>
     */
    public static function integratedAuthorityStates(): iterable
    {
        foreach (
            [
            'anonymous', 'cookie only', 'invalid signature', 'revoked', 'stale', 'pending', 'disabled', 'deleted',
            'idle expired', 'absolute expired', 'missing role', 'wrong owner', 'missing session', 'authority snapshot'
            ] as $state
        ) {
            yield $state => [$state];
        }
    }

    /**
     * Varies each grant independently across real routes without granting authority by managed role name
     *
     * @param string $role
     * @param array<int, string> $grants
     *
     * @phpstan-param list<string> $grants
     */
    #[DataProvider('integratedPermissionSets')]
    public function testIntegratedPermissionDenials(string $role, array $grants): void
    {
        $this->reconcileMatrixPolicy();
        $this->assignMatrixRole($role);
        $this->connection->executeStatement(
            'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name NOT IN (?))',
            [$grants === [] ? ['NO_PERMISSION'] : $grants],
            [ArrayParameterType::STRING]
        );
        $before = $this->matrixBusinessSnapshot();
        foreach (self::protectedOperations() as [$method, $path, $permission]) {
            if ($permission === null || in_array($permission, $grants, true)) {
                continue;
            }
            $this->assertMatrixDenial($this->request($this->token, $method, $this->matrixPath($path)), 403);
        }
        self::assertSame($before, $this->matrixBusinessSnapshot());
        self::assertSame(0, $this->queries);
        self::assertSame([], $this->logs->getRecords());
    }

    /**
     * Supplies empty, ordinary, independent single and amended invitation permission combinations
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function integratedPermissionSets(): iterable
    {
        yield 'misleading managed name without grants' => ['ROLE_SUPER_ADMIN', []];
        foreach (self::managedMemberships() as $name => [$role, $permissions]) {
            if ($name === 'ordinary') {
                yield 'ordinary' => [$role, $permissions];
            } else {
                foreach ($permissions as $permission) {
                    yield $permission.' alone' => [$role, [$permission]];
                }
            }
        }
        yield 'read and manage invitations' => ['ROLE_SUPER_ADMIN', ['READ_USERS', 'MANAGE_USER_INVITATIONS']];
        yield 'invite and elevate' => ['ROLE_SUPER_ADMIN', ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN']];
    }

    /**
     * Challenges every credential mutation's real transport profile before admission or durable writes
     */
    #[DataProvider('integratedRequestViolations')]
    public function testIntegratedRequestControls(string $violation): void
    {
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $clock = new class ($this->tokens->clock) implements CsrfClock {
            /**
             * Constructs a shared fixed security clock
             */
            public function __construct(private readonly AccessClock $clock)
            {
            }

            /**
             * @inheritDoc
             */
            public function now(): int
            {
                return $this->clock->now()->getTimestamp();
            }
        };
        $container->set(CsrfClock::class, static fn(): CsrfClock => $clock);
        $nonce = bin2hex(random_bytes(32));
        $proof = $container->get(HmacCsrfProofs::class)->sign($nonce, $clock->now() + 900);
        $password = 'Aa1!'.bin2hex(random_bytes(16));
        $credential = bin2hex(random_bytes(32));
        $headers = [
            'Content-Type'   => 'application/json',
            'Origin'         => 'https://agent-os.test',
            'Sec-Fetch-Site' => 'same-origin',
            'X-CSRF-Proof'   => $proof,
            'Cookie'         => CsrfCookie::NAME.'='.$nonce
        ];
        $changes = match ($violation) {
            'foreign origin' => ['Origin' => 'https://foreign.test'],
            'null origin' => ['Origin' => 'null'],
            'foreign origin port' => ['Origin' => 'https://agent-os.test:444'],
            'cross-site fetch' => ['Sec-Fetch-Site' => 'cross-site'],
            'navigate' => ['Sec-Fetch-Mode' => 'navigate'],
            'foreign destination' => ['Sec-Fetch-Dest' => 'document'],
            'forwarded authority' => ['Forwarded' => 'proto=https'],
            'content type' => ['Content-Type' => 'text/plain'],
            default => []
        };
        $headers = array_replace($headers, $changes);
        if ($violation === 'missing origin') {
            unset($headers['Origin']);
        }
        // Mount the actual route graph without dispatching a business operation.
        $this->request(null);
        $before = $this->matrixBusinessSnapshot();
        $possession = [
            'user_id'               => $this->user->getId()->toString(),
            'credential'            => $credential,
            'password'              => $password,
            'password_confirmation' => $password
        ];
        $bodies = [
            'login'                   => [
                'email' => 'current@example.test', 'password' => $password, 'remember' => false
            ],
            'refresh'                 => [],
            'logout'                  => [],
            'activate'                => $possession,
            'password-reset'          => ['email' => 'current@example.test'],
            'password-reset/complete' => $possession,
            'password/change'         => [
                'current_password'      => $password,
                'password'              => $password,
                'password_confirmation' => $password
            ]
        ];
        foreach ($bodies as $operation => $body) {
            $uri = ($violation === 'http' ? 'http' : 'https').'://agent-os.test/api/v1/auth/'.$operation;
            if ($violation === 'query source') {
                $uri .= '?credential=alternate';
            }
            $request = new ServerRequestFactory()->createServerRequest('POST', $uri, ['REMOTE_ADDR' => '192.0.2.1']);
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
            if ($operation === 'password/change') {
                $request = $request->withHeader('Authorization', 'Bearer '.$this->token);
            }
            $request->getBody()->write(
                $violation === 'array body' ? '[]' : json_encode((object) $body, JSON_THROW_ON_ERROR)
            );
            $response = $this->app->handle($request);
            self::assertSame(
                in_array($violation, ['content type', 'query source', 'array body'], true) ? 400 : 403,
                $response->getStatusCode(),
                $operation.' / '.$violation
            );
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            $surfaces = json_encode($response->getHeaders(), JSON_THROW_ON_ERROR).(string) $response->getBody();
            $surfaces .= json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
            foreach ([$password, $credential, $nonce, $proof, $this->token] as $secret) {
                self::assertStringNotContainsString($secret, $surfaces);
            }
        }
        self::assertSame($before, $this->matrixBusinessSnapshot());
        self::assertSame(0, $this->queries);
    }

    /**
     * Supplies transport, browser provenance and input-source violations independently
     *
     * @return iterable<string, array{string}>
     */
    public static function integratedRequestViolations(): iterable
    {
        foreach (
            [
            'foreign origin', 'null origin', 'foreign origin port', 'missing origin', 'cross-site fetch', 'navigate',
            'foreign destination', 'forwarded authority', 'content type', 'query source', 'array body', 'http'
            ] as $violation
        ) {
            yield $violation => [$violation];
        }
    }

    /**
     * Enumerates actual protected HTTP interactions independently of their implementation declarations
     *
     * @return list<array{string, string, ?string}>
     */
    private static function protectedOperations(): array
    {
        return [
            ['GET', '/documentation/bootstrap', 'READ_SWAGGER'],
            ['GET', '/documentation/openapi', 'READ_SWAGGER'],
            ['POST', '/auth/password/change', null],
            ['POST', '/invitations', 'INVITE_USERS'],
            ['POST', '/invitations/{user}/retry', 'MANAGE_USER_INVITATIONS'],
            ['POST', '/invitations/{user}/resend', 'MANAGE_USER_INVITATIONS'],
            ['PATCH', '/invitations/{user}/email', 'MANAGE_USER_INVITATIONS'],
            ['GET', '/invitations/{user}', 'READ_USERS'],
            ['POST', '/workspaces/{user}/projects', 'REGISTER_PROJECTS'],
            ['POST', '/workspaces/{user}/projects/conflict-preview', 'READ_PROJECTS'],
            ['GET', '/workspaces/{user}/projects/{session}', 'READ_PROJECTS'],
            ['GET', '/workspaces/{user}/projects', 'READ_PROJECTS'],
            ['POST', '/workspaces/{user}/references', 'GENERATE_REFERENCE_IDS'],
            ['POST', '/roles', 'CREATE_ROLES'],
            ['PATCH', '/roles/{user}', 'UPDATE_ROLES'],
            ['DELETE', '/roles/{user}', 'DELETE_ROLES'],
            ['GET', '/roles', 'READ_ROLES'],
            ['GET', '/roles/{user}', 'READ_ROLES'],
            ['GET', '/permissions', 'READ_PERMISSIONS'],
            ['GET', '/permissions/{user}', 'READ_PERMISSIONS'],
            ['DELETE', '/me/sessions/{session}', 'DELETE_OWN_SESSIONS'],
            ['GET', '/me/sessions', 'READ_OWN_SESSIONS'],
            ['GET', '/users/{user}/sessions', 'READ_SESSIONS'],
            ['DELETE', '/users/{user}/sessions/{session}', 'DELETE_SESSIONS'],
            ['GET', '/users', 'READ_USERS'],
            ['GET', '/users/{user}', 'READ_USERS'],
            ['DELETE', '/users/{user}', 'DELETE_USERS'],
            ['POST', '/users/{user}/restore', 'RESTORE_USERS'],
            ['POST', '/users/{user}/disable', 'DISABLE_USERS'],
            ['POST', '/users/{user}/enable', 'ENABLE_USERS'],
            ['GET', '/me', null]
        ];
    }

    /**
     * Resolves only generated package fixture identities into actual request paths
     */
    private function matrixPath(string $path): string
    {
        return '/api/v1'.strtr($path, [
            '{user}' => $this->user->getId()->toString(), '{session}' => $this->session->getId()->toString()
        ]);
    }

    /**
     * Prepares the real managed catalog before challenging operation admission
     */
    private function reconcileMatrixPolicy(): void
    {
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $container->get(SynchronousCommandBus::class)->execute(new ReconcileManagedPolicy(
            $container->get(\Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy::class)
        ));
    }

    /**
     * Assigns a managed fixture membership without changing its token authority
     */
    private function assignMatrixRole(string $name): void
    {
        $role = $this->roles->getByName(RoleName::fromString($name));
        self::assertNotNull($role);
        $replacement = clone $this->user;
        $replacement->assignRole($role->getId(), $this->tokens->clock->now());
        self::assertTrue($this->connection->transactional(
            fn() => $this->users->replaceRoleAssignments($this->user, $replacement)
        ));
    }

    /**
     * Compares complete business rows without retaining credential-bearing snapshots in reports
     *
     * @return array<string, string>
     */
    private function matrixBusinessSnapshot(): array
    {
        $snapshots = [];
        $tables = $this->connection->fetchFirstColumn(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
        );
        foreach ($tables as $table) {
            $query = 'SELECT row_to_json(r)::text FROM '.$this->connection->quoteIdentifier($table);
            $query .= ' r ORDER BY row_to_json(r)::text';
            $snapshots[$table] = hash('sha256', json_encode(
                $this->connection->fetchFirstColumn($query),
                JSON_THROW_ON_ERROR
            ));
        }

        return $snapshots;
    }

    /**
     * Checks exact safe denial and credential-free response and diagnostic surfaces
     */
    private function assertMatrixDenial(ResponseInterface $response, int $status): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame([
            'status' => 'fail',
            'data'   => ['message' => $status === 401 ? 'Authentication required.' : 'Permission denied.']
        ], json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertFalse($response->hasHeader('Set-Cookie'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        $surface = json_encode($response->getHeaders(), JSON_THROW_ON_ERROR).(string) $response->getBody();
        foreach ([$this->token, $this->user->getPasswordHash()->toString()] as $secret) {
            self::assertStringNotContainsString($secret, $surface);
        }
    }

    /**
     * Sends only actual registered route requests
     */
    private function request(
        ?string $token,
        string $method = 'GET',
        string $path = '/api/v1/me',
        bool $cookieOnly = false
    ): ResponseInterface {
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

        if ($cookieOnly) {
            $request = $request->withHeader('Cookie', 'access_token='.$this->token.'; refresh_token='.$this->token);
        }

        $response = $this->app->handle($request);
        $this->assertSecretObservability($response, $this->connection, $this->logs, [$this->token]);

        return $response;
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
