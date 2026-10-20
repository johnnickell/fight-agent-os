<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Action\Api\V1\Authority\GetPermissionAction;
use App\Adapter\Http\Action\Api\V1\Authority\GetRoleAction;
use App\Adapter\Http\Action\Api\V1\Authority\ListPermissionsAction;
use App\Adapter\Http\Action\Api\V1\Authority\ListRolesAction;
use App\Adapter\Http\Responder\Api\V1\Authority\CatalogResponder;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Adapter\Security\AccessToken\AccessJwt;
use App\Application\Authorization\ReadAuthorityCatalogs;
use App\Application\Failure\PermissionDenied;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use App\Domain\Authorization\InstallationManagedPolicy;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
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
use Tests\Support\CatalogQueryProbe;
use Tooling\OpenApi\Document;

/**
 * Exercises the real guarded catalogs, configured query pipeline and PostgreSQL authority
 */
final class AuthorityCatalogApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private User $actor;
    private RefreshSession $session;
    private string $token;
    /** @var array<string, string|false> */
    private array $environment = [];
    private CatalogQueryProbe $probe;

    /**
     * Boots actual routes against the guarded task test database
     */
    protected function setUp(): void
    {
        foreach (['DATABASE_URL', 'APP_ACCESS_TOKEN_CONFIG'] as $name) {
            $this->environment[$name] = getenv($name);
        }
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection(new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ])->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement('TRUNCATE users, roles, permissions, audit_evidence CASCADE');
        $tokens = new AccessTokens();
        $tokens->clock->time = new DateTimeImmutable('2026-10-20T12:00:00Z');
        $now = $tokens->clock->now()->modify('-1 minute');
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
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('catalog@example.test'), $now);
        $this->actor->activate(
            PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_BCRYPT, ['cost' => 4])),
            $now
        );
        $this->actor->assignRole(RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID), $now);
        $container->get(Connection::class)->transactional(
            fn() => $container->get(UserRepository::class)->add($this->actor)
        );
        $this->session = RefreshSession::start(
            RefreshSessionId::generate(),
            $this->actor->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            $this->actor->getAuthenticationVersion(),
            false
        );
        $this->connection->transactional(fn() => RefreshSessionRecords::insert($this->connection, $this->session));
        $claims = $tokens->claims();
        $claims['sub'] = $this->actor->getId()->toString();
        $claims['sid'] = $this->session->getId()->toString();
        $claims['auth_version'] = $this->actor->getAuthenticationVersion();
        $this->token = $tokens->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        $this->probe = new CatalogQueryProbe();
        $container->get('messaging.query.bus')->addFilter($this->probe);
    }

    /**
     * Restores environment without touching another runtime or database
     */
    protected function tearDown(): void
    {
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        $this->connection->close();
    }

    /**
     * Allows each exact permission and denies the other catalog before dispatch
     */
    #[DataProvider('catalogs')]
    public function testPermissionMatrix(string $catalog, string $required): void
    {
        $id = $this->connection->fetchOne('SELECT id FROM '.$catalog.' ORDER BY id LIMIT 1');
        foreach (['/api/v1/'.$catalog, '/api/v1/'.$catalog.'/'.$id] as $path) {
            $this->assertResponse($this->request($path, false), 401);
            $this->assertQueries(0);
        }
        $this->connection->executeStatement(
            'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = ?)',
            [$required]
        );
        foreach (['/api/v1/'.$catalog, '/api/v1/'.$catalog.'/'.$id, '/api/v1/'.$catalog.'/invalid'] as $path) {
            $this->assertResponse($this->request($path), 403);
            $this->assertQueries(0);
        }
        $other = $catalog === 'roles' ? 'permissions' : 'roles';
        $this->assertResponse($this->request('/api/v1/'.$other), 200);
        $this->assertQueries(1);
    }

    /**
     * Supplies distinct read authority names
     *
     * @return iterable<array{string, string}>
     */
    public static function catalogs(): iterable
    {
        yield ['roles', 'READ_ROLES'];
        yield ['permissions', 'READ_PERMISSIONS'];
    }

    /**
     * Returns explicit managed/custom Views and deterministic disjoint tied-timestamp pages
     */
    public function testSafeViewsAndPagination(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $permission = Permission::define(PermissionId::generate(), PermissionName::fromString('CUSTOM_READ'), $now);
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_CUSTOM'), [$permission->getId()], $now);
        $this->container->get(Connection::class)->transactional(function () use ($role, $permission): void {
            $this->container->get(PermissionRepository::class)->add($permission);
            $this->container->get(RoleRepository::class)->add($role);
        });
        foreach (['roles', 'permissions'] as $catalog) {
            $expected = $this->connection->fetchFirstColumn('SELECT id FROM '.$catalog.' ORDER BY created_at, id');
            $ids = [];
            foreach (range(1, count($expected)) as $page) {
                $path = '/api/v1/'.$catalog.'?page='.$page.'&per_page=1';
                $response = $this->request($path);
                $data = $this->assertResponse($response, 200)['data'];
                self::assertSame($page, $data['page']);
                self::assertSame(1, $data['per_page']);
                self::assertCount(1, $data['records']);
                self::assertSame($data, $this->assertResponse($this->request($path), 200)['data']);
                $record = $data['records'][0];
                $key = $catalog === 'roles' ? 'role_id' : 'permission_id';
                $ids[] = $record[$key];
                $single = $this->assertResponse($this->request('/api/v1/'.$catalog.'/'.$record[$key]), 200)['data'];
                self::assertSame($record, $single);
                $fields = ['role_id', 'name', 'managed'];
                if ($catalog === 'permissions') {
                    $fields = ['permission_id', 'name', 'tier', 'managed'];
                }
                self::assertSame($fields, array_keys($record));
            }
            self::assertSame($expected, $ids);
            $response = $this->request('/api/v1/'.$catalog.'?page=1000&per_page=100');
            $empty = $this->assertResponse($response, 200)['data'];
            self::assertSame([], $empty['records']);
        }
        $custom = $this->assertResponse($this->request('/api/v1/roles/'.$role->getId()->toString()), 200)['data'];
        self::assertSame([
            'role_id' => $role->getId()->toString(), 'name' => 'ROLE_CUSTOM', 'managed' => false
        ], $custom);
        $response = $this->request('/api/v1/permissions/'.$permission->getId()->toString());
        $custom = $this->assertResponse($response, 200)['data'];
        self::assertSame('ADMIN_SAFE', $custom['tier']);
        self::assertFalse($custom['managed']);
    }

    /**
     * Rejects invalid sources, IDs, bounds and ordering before package queries
     */
    #[DataProvider('invalidInput')]
    public function testInput(string $suffix): void
    {
        foreach (['roles', 'permissions'] as $catalog) {
            $this->assertResponse($this->request('/api/v1/'.$catalog.$suffix), 400);
            $this->assertQueries(0);
        }
    }

    /**
     * Supplies real query parser and path failures
     *
     * @return iterable<array{string}>
     */
    public static function invalidInput(): iterable
    {
        $suffixes = [
            '/invalid', '?page=0', '?page=1001', '?per_page=0', '?per_page=101', '?page=-1', '?page=1.5',
            '?page=1&page=2', '?per_page[]=1', '?order=name', '?page=', '?page=%GG'
        ];
        foreach ($suffixes as $suffix) {
            yield [$suffix];
        }
    }

    /**
     * Keeps authorized absence safe and refuses all catalog mutation methods
     */
    public function testNotFoundAndNoMutations(): void
    {
        $targets = ['roles' => RoleId::generate()->toString(), 'permissions' => PermissionId::generate()->toString()];
        foreach ($targets as $catalog => $id) {
            $this->assertResponse($this->request('/api/v1/'.$catalog.'/'.$id), 404);
            foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
                foreach (['/api/v1/'.$catalog, '/api/v1/'.$catalog.'/'.$id] as $path) {
                    self::assertSame(405, $this->request($path, true, $method)->getStatusCode());
                }
            }
        }
        $this->assertQueries(2);
    }

    /**
     * Rechecks changed protected policy after HTTP's early gate at every Application entry
     */
    public function testConcurrentPermissionChange(): void
    {
        // Establishes a valid early HTTP snapshot, then commits a change on the independent connection.
        $this->assertResponse($this->request('/api/v1/roles'), 200);
        $this->connection->executeStatement("UPDATE permissions SET tier = 'ADMIN_SAFE' WHERE name = 'READ_ROLES'");
        $authentication = new AuthenticationContext($this->actor->getId(), $this->session->getId(), 1);
        try {
            $this->container->get(ReadAuthorityCatalogs::class)->roles($authentication);
            self::fail('Changed protected tier was accepted.');
        } catch (PermissionDenied) {
            $this->assertQueries(1);
        }
        $this->assertResponse($this->request('/api/v1/roles'), 403);
        $this->assertQueries(1);
    }

    /**
     * Denies a permission removal committed between HTTP authorization and each actual Action
     */
    #[DataProvider('dispatchRaces')]
    public function testChangeAfterEarlyGate(string $action, string $path, string $permission): void
    {
        $this->container->set($action, function (Container $container) use ($action, $permission) {
            $this->connection->executeStatement(
                'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = ?)',
                [$permission]
            );

            return new $action(
                $container->get(ReadAuthorityCatalogs::class),
                $container->get(CatalogResponder::class)
            );
        });
        $this->assertResponse($this->request($path), 403);
        $this->assertQueries(0);
    }

    /**
     * Supplies every real catalog Action and its distinct protected read capability
     *
     * @return iterable<array{class-string, string, string}>
     */
    public static function dispatchRaces(): iterable
    {
        yield [ListRolesAction::class, '/api/v1/roles', 'READ_ROLES'];
        yield [GetRoleAction::class, '/api/v1/roles/'.RoleId::generate()->toString(), 'READ_ROLES'];
        yield [ListPermissionsAction::class, '/api/v1/permissions', 'READ_PERMISSIONS'];
        yield [
            GetPermissionAction::class,
            '/api/v1/permissions/'.PermissionId::generate()->toString(),
            'READ_PERMISSIONS'
        ];
    }

    /**
     * Removes stale assignment authority between successive real requests
     */
    public function testRevokedAuthority(): void
    {
        $this->assertResponse($this->request('/api/v1/permissions'), 200);
        $this->connection->executeStatement(
            'DELETE FROM user_role_assignments WHERE user_id = ?',
            [$this->actor->getId()->toString()]
        );
        $this->assertResponse($this->request('/api/v1/permissions'), 403);
        $this->assertQueries(1);
    }

    /**
     * Checks the observable configured bus dispatch count after real request handling
     */
    private function assertQueries(int $expected): void
    {
        self::assertSame($expected, $this->probe->count());
    }

    /**
     * Sends real request source bytes through authentication and post-routing validation
     */
    private function request(string $path, bool $authenticated = true, string $method = 'GET'): ResponseInterface
    {
        $request = new ServerRequestFactory()->createServerRequest($method, 'https://agent-os.test'.$path, [
            'REQUEST_URI' => $path
        ]);
        if ($authenticated) {
            $request = $request->withHeader('Authorization', 'Bearer '.$this->token);
        }

        return $this->app->handle($request);
    }

    /**
     * Validates real response schemas, privacy headers and exact redacted output
     *
     * @return array<string, mixed>
     */
    private function assertResponse(ResponseInterface $response, int $status): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $response->getHeaderLine('X-Correlation-ID'));
        self::assertFalse($response->hasHeader('Set-Cookie'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        $body = (string) $response->getBody();
        $privateValues = [
            'permission_ids', 'created_at', 'updated_at', 'password', $this->token, $this->session->getId()->toString()
        ];
        foreach ($privateValues as $private) {
            self::assertStringNotContainsString($private, $body);
        }
        // Validates shared failures and the actual safe result shapes against the owned contract.
        static $spec = null;
        $spec ??= Document::generate();
        $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        $path = $status === 404 ? '/api/v1/roles/{roleId}' : '/api/v1/roles';
        if ($status === 200) {
            $data = $decoded['data'];
            $single = !isset($data['records']);
            $record = $single ? $data : ($data['records'][0] ?? null);
            $catalog = isset($record['role_id']) ? 'roles' : 'permissions';
            $path = '/api/v1/'.$catalog.($single ? ($catalog === 'roles' ? '/{roleId}' : '/{permissionId}') : '');
        }
        (new ValidatorBuilder())->fromJson($spec)->getResponseValidator()->validate(
            new OperationAddress($path, 'get'),
            $response
        );

        return $decoded;
    }
}
