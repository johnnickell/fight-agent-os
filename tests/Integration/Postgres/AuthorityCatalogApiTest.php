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
use Fight\AccessControl\Domain\AccessControl\User\Command\DeleteUser;
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
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Level;
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
                $fields = ['role_id', 'name', 'managed', 'revision'];
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
            'role_id'  => $role->getId()->toString(),
            'name'     => 'ROLE_CUSTOM',
            'managed'  => false,
            'revision' => $role->getRevision()->toString()
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
     * Keeps absence safe and refuses undeclared catalog mutation methods
     */
    public function testNotFoundAndNoMutations(): void
    {
        $targets = ['roles' => RoleId::generate()->toString(), 'permissions' => PermissionId::generate()->toString()];
        foreach ($targets as $catalog => $id) {
            $this->assertResponse($this->request('/api/v1/'.$catalog.'/'.$id), 404);
            foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
                foreach (['/api/v1/'.$catalog, '/api/v1/'.$catalog.'/'.$id] as $path) {
                    $declared = $catalog === 'roles' && (($method === 'POST' && $path === '/api/v1/roles')
                        || (in_array($method, ['PATCH', 'DELETE'], true) && $path !== '/api/v1/roles'));
                    self::assertSame($declared ? 400 : 405, $this->request($path, true, $method)->getStatusCode());
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
     * Creates from a name only and returns the generated identity and revision actually persisted
     */
    public function testNameOnlyCreation(): void
    {
        $created = $this->mutation('POST', '/api/v1/roles', ['name' => 'ROLE_GENERATED'], 201)['data'];
        $id = RoleId::fromString($created['role_id']);
        $stored = $this->container->get(RoleRepository::class)->getById($id);
        self::assertNotNull($stored);
        self::assertSame('ROLE_GENERATED', $stored->getName()->toString());
        self::assertFalse($stored->isManaged());
        self::assertSame($stored->getRevision()->toString(), $created['revision']);
        self::assertSame($id->toString(), $this->connection->fetchOne(
            "SELECT subject_id FROM audit_evidence WHERE action = 'role.custom_created'"
        ));
        $other = $this->mutation('POST', '/api/v1/roles', ['name' => 'ROLE_ANOTHER'], 201)['data'];
        self::assertFalse($id->equals(RoleId::fromString($other['role_id'])));
        self::assertSame(2, $this->roleAuditCount());
    }

    /**
     * Rejects even a valid supplied creation identity without persistence or audit
     */
    public function testRejectsCallerCreationId(): void
    {
        $id = RoleId::generate()->toString();
        $this->mutation('POST', '/api/v1/roles', ['name' => 'ROLE_INJECTED', 'role_id' => $id], 400);
        self::assertFalse($this->connection->fetchOne('SELECT id FROM roles WHERE id = ?', [$id]));
        self::assertSame(0, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM roles WHERE name = 'ROLE_INJECTED'"
        ));
        self::assertSame(0, $this->roleAuditCount());
    }

    /**
     * Carries exact displayed revisions through create, no-op rename, stale conflict and confirmed removal
     */
    public function testCustomRoleJourney(): void
    {
        $created = $this->mutation('POST', '/api/v1/roles', ['name' => 'ROLE_CUSTOM'], 201);
        $id = $created['data']['role_id'];
        $revision = $created['data']['revision'];
        $same = $this->mutation(
            'PATCH',
            '/api/v1/roles/'.$id,
            ['name' => 'ROLE_CUSTOM', 'expected_revision' => $revision],
            200
        );
        self::assertSame($created['data'], $same['data']);
        self::assertSame(1, $this->roleAuditCount());
        $renamed = $this->mutation(
            'PATCH',
            '/api/v1/roles/'.$id,
            ['name' => 'ROLE_RENAMED', 'expected_revision' => $revision],
            200
        );
        self::assertNotSame($revision, $renamed['data']['revision']);
        $this->mutation(
            'DELETE',
            '/api/v1/roles/'.$id,
            ['expected_revision' => $renamed['data']['revision'], 'confirm' => false],
            400
        );
        $this->mutation(
            'DELETE',
            '/api/v1/roles/'.$id,
            ['expected_revision' => $renamed['data']['revision'], 'confirm' => true],
            200
        );
        self::assertFalse($this->connection->fetchOne('SELECT id FROM roles WHERE id = ?', [$id]));
        $audits = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT actor_id, action, subject_id, context FROM audit_evidence WHERE subject_type = 'role' ORDER BY id
SQL);
        self::assertSame(
            ['role.custom_created', 'role.custom_renamed', 'role.custom_removed'],
            array_column($audits, 'action')
        );
        foreach ($audits as $audit) {
            self::assertSame($this->actor->getId()->toString(), $audit['actor_id']);
            self::assertSame($id, $audit['subject_id']);
            self::assertSame('{}', $audit['context']);
        }
    }

    /**
     * Rejects stale intent atomically without recording success or substituting current authority
     */
    #[DataProvider('staleOperations')]
    public function testStaleRoleIntent(string $method): void
    {
        $created = $this->mutation('POST', '/api/v1/roles', ['name' => 'ROLE_CUSTOM'], 201);
        $id = $created['data']['role_id'];
        $current = $this->mutation(
            'PATCH',
            '/api/v1/roles/'.$id,
            ['name' => 'ROLE_RENAMED', 'expected_revision' => $created['data']['revision']],
            200
        );
        $body = $method === 'DELETE' ? ['confirm' => true] : ['name' => 'ROLE_RENAMED'];
        $body['expected_revision'] = $created['data']['revision'];
        $this->mutation($method, '/api/v1/roles/'.$id, $body, 409);
        self::assertSame($current['data']['revision'], $this->connection->fetchOne(
            'SELECT revision FROM roles WHERE id = ?',
            [$id]
        ));
        self::assertSame(2, $this->roleAuditCount());
    }

    /**
     * Supplies both stale token mutations
     *
     * @return iterable<array{string}>
     */
    public static function staleOperations(): iterable
    {
        yield ['PATCH'];
        yield ['DELETE'];
    }

    /**
     * Accepts only each exact operation permission, independently of catalog visibility
     */
    #[DataProvider('roleOperations')]
    public function testMutationPermissions(string $method, string $required): void
    {
        $created = $this->mutation('POST', '/api/v1/roles', ['name' => 'ROLE_CUSTOM'], 201);
        $id = $created['data']['role_id'];
        $this->connection->executeStatement(
            'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name <> ?)',
            [$required]
        );
        $path = $method === 'POST' ? '/api/v1/roles' : '/api/v1/roles/'.$id;
        $body = match ($method) {
            'POST' => ['name' => 'ROLE_OTHER'],
            'PATCH' => ['name' => 'ROLE_RENAMED', 'expected_revision' => $created['data']['revision']],
            default => ['confirm' => true, 'expected_revision' => $created['data']['revision']]
        };
        $this->mutation($method, $path, $body, $method === 'POST' ? 201 : 200);
        $this->connection->executeStatement('DELETE FROM role_permissions');
        $this->mutation($method, $path, $body, 403);
        $authentication = new AuthenticationContext($this->actor->getId(), $this->session->getId(), 1);
        try {
            $this->container->get(\App\Application\Authorization\ManageCustomRoles::class)->create(
                $authentication,
                RoleName::fromString('ROLE_DENIED')
            );
            self::fail('Direct Application caller bypassed authorization.');
        } catch (PermissionDenied) {
            self::assertSame(0, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM roles WHERE name = 'ROLE_DENIED'"
            ));
        }
    }

    /**
     * Supplies the operation permission matrix
     *
     * @return iterable<array{string, string}>
     */
    public static function roleOperations(): iterable
    {
        yield ['POST', 'CREATE_ROLES'];
        yield ['PATCH', 'UPDATE_ROLES'];
        yield ['DELETE', 'DELETE_ROLES'];
    }

    /**
     * Rejects spoofed actors, injected grants, duplicate names, managed targets and retained assignments
     */
    public function testMutationSecurity(): void
    {
        $injected = ['actor_id' => $this->actor->getId()->toString(), 'permission_ids' => [], 'managed' => false];
        foreach ($injected as $field => $value) {
            $this->mutation(
                'POST',
                '/api/v1/roles',
                ['name' => 'ROLE_CUSTOM', $field => $value],
                400
            );
        }
        self::assertSame(0, $this->roleAuditCount());
        self::assertSame(0, (int) $this->connection->fetchOne("SELECT count(*) FROM roles WHERE name = 'ROLE_CUSTOM'"));
        $created = $this->mutation('POST', '/api/v1/roles', ['name' => 'ROLE_CUSTOM'], 201);
        $id = $created['data']['role_id'];
        $this->mutation('POST', '/api/v1/roles', ['name' => 'ROLE_CUSTOM'], 409);
        $this->mutation('PATCH', '/api/v1/roles/'.$id, ['name' => 'ROLE_OTHER'], 400);
        $this->mutation('PATCH', '/api/v1/roles/'.$id, ['name' => 'ROLE_OTHER', 'expected_revision' => 'invalid'], 400);
        $managed = $this->container->get(RoleRepository::class)->getById(
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID)
        );
        $this->mutation(
            'PATCH',
            '/api/v1/roles/'.$managed->getId()->toString(),
            ['name' => 'ROLE_OTHER', 'expected_revision' => $managed->getRevision()->toString()],
            409
        );
        $this->mutation(
            'DELETE',
            '/api/v1/roles/'.$managed->getId()->toString(),
            ['confirm' => true, 'expected_revision' => $managed->getRevision()->toString()],
            409
        );
        $user = User::invite(
            UserId::generate(),
            EmailAddress::fromString('reference@example.test'),
            new DateTimeImmutable('2026-10-20T12:00:00Z')
        );
        $user->assignRole(RoleId::fromString($id), $user->getCreatedAt());
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(UserRepository::class)->add($user)
        );
        $this->mutation(
            'DELETE',
            '/api/v1/roles/'.$id,
            ['confirm' => true, 'expected_revision' => $created['data']['revision']],
            409
        );
        self::assertSame($id, $this->connection->fetchOne('SELECT id FROM roles WHERE id = ?', [$id]));
    }

    /**
     * Rolls back Role persistence when required consumer audit fails
     */
    public function testRoleAuditRollback(): void
    {
        $logs = $this->captureExpectedFailure();
        $audit = $this->createStub(\App\Domain\Authorization\RoleMutationAudit::class);
        $audit->method('record')->willThrowException(new \RuntimeException('private audit diagnostics'));
        $this->container->set(\App\Domain\Authorization\RoleMutationAudit::class, static fn() => $audit);
        $response = $this->mutation('POST', '/api/v1/roles', ['name' => 'ROLE_ROLLBACK'], 500);
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], $response);
        $this->assertSanitizedFailure($logs);
        self::assertSame(0, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM roles WHERE name = 'ROLE_ROLLBACK'"
        ));
        self::assertSame(0, $this->roleAuditCount());
    }

    /**
     * Cancels reset authority and material with lifecycle mutation and required audit on the real shared graph
     */
    #[DataProvider('lifecycleOperations')]
    public function testRecoveryLifecycle(string $command, bool $auditFails): void
    {
        $now = new DateTimeImmutable('2026-10-20T12:00:00Z');
        $target = User::invite(UserId::generate(), EmailAddress::fromString('recovery@example.test'), $now);
        $target->activate(
            PasswordHash::fromString((string) password_hash('fixture', PASSWORD_BCRYPT, ['cost' => 4])),
            $now
        );
        $grants = $this->container->get(
            \Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository::class
        );
        $grant = \Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant::issue(
            $target->getId(),
            \Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential::fromString(
                bin2hex(random_bytes(32))
            ),
            $now,
            $now->modify('+1 hour'),
            $target->getEmail(),
            'fixture-encrypted-material',
            $target->getRecoveryAuthority()
        );
        $this->container->get(Connection::class)->transactional(function () use ($target, $grant, $grants): void {
            $this->container->get(UserRepository::class)->add($target);
            self::assertTrue($grants->add($grant));
        });
        if ($auditFails) {
            $audit = $this->createStub(\Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository::class);
            $audit->method('add')->willThrowException(new \RuntimeException('private audit diagnostics'));
            $this->container->set(
                \Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository::class,
                static fn() => $audit
            );
        }
        $message = match ($command) {
            'disable' => new DisableUser($this->actor->getId(), $target->getId()),
            default => new DeleteUser($this->actor->getId(), $target->getId())
        };
        try {
            $this->container->get(CommandBus::class)->execute($message);
            self::assertFalse($auditFails);
        } catch (\RuntimeException) {
            self::assertTrue($auditFails);
        }
        $stored = $this->container->get(UserRepository::class)->getById($target->getId());
        $reset = $grants->getById($grant->getId());
        self::assertSame(
            $target->getAuthenticationVersion() + ($auditFails ? 0 : 1),
            $stored->getAuthenticationVersion()
        );
        self::assertSame($auditFails, $reset->isIssued());
        self::assertSame($auditFails, $reset->getDelivery()->hasRecoverableMaterial());
        self::assertSame($auditFails ? 0 : 1, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM audit_evidence WHERE subject_id = ?',
            [$target->getId()->toString()]
        ));
    }

    /**
     * Supplies both lifecycle writers and atomic-audit failure schedules
     *
     * @return iterable<array{string, bool}>
     */
    public static function lifecycleOperations(): iterable
    {
        foreach (['disable', 'delete'] as $command) {
            yield [$command, false];
            yield [$command, true];
        }
    }

    /**
     * Retains committed Role and audit when success and failure publication both fail
     */
    public function testRolePublicationFailure(): void
    {
        $logs = $this->captureExpectedFailure();
        $events = $this->createStub(\Fight\Common\Application\Messaging\Event\EventDispatcher::class);
        $events->method('trigger')->willThrowException(new \RuntimeException('private publisher diagnostic'));
        $this->container->set(\Fight\Common\Application\Messaging\Event\EventDispatcher::class, static fn() => $events);
        $result = $this->mutation('POST', '/api/v1/roles', ['name' => 'ROLE_COMMITTED'], 500);
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], $result);
        $this->assertSanitizedFailure($logs);
        $id = $this->connection->fetchOne("SELECT id FROM roles WHERE name = 'ROLE_COMMITTED'");
        self::assertIsString($id);
        self::assertSame($id, $this->connection->fetchOne(
            "SELECT subject_id FROM audit_evidence WHERE action = 'role.custom_created'"
        ));
        self::assertSame(1, $this->roleAuditCount());
    }

    /**
     * Captures only deliberately injected HTTP failures without changing ordinary error visibility
     */
    private function captureExpectedFailure(): TestHandler
    {
        $logs = new TestHandler(Level::Error);
        $logs->setFormatter(new JsonFormatter());
        $this->container->get('http.failure.logger')->setHandlers([$logs]);

        return $logs;
    }

    /**
     * Requires one correlated safe record while excluding private failure, credential and identity material
     */
    private function assertSanitizedFailure(TestHandler $logs): void
    {
        self::assertCount(1, $logs->getRecords());
        $record = $logs->getRecords()[0];
        self::assertSame(Level::Error, $record->level);
        self::assertSame('http-failure', $record->channel);
        self::assertSame('Unhandled HTTP request failure.', $record->message);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $record->context['correlation_id']);
        self::assertSame([
            'correlation_id'     => $record->context['correlation_id'],
            'correlation_source' => 'generated',
            'request_method'     => 'POST',
            'exception_type'     => \RuntimeException::class
        ], $record->context);
        self::assertSame([], $record->extra);
        self::assertIsString($record->formatted);
        $secrets = ['private', $this->token, $this->actor->getId()->toString(), $this->session->getId()->toString()];
        foreach ($secrets as $secret) {
            self::assertStringNotContainsString($secret, $record->formatted);
        }
    }

    /**
     * Sends real mutation bytes and validates the actual operation response contract
     *
     * @phpstan-param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function mutation(string $method, string $path, array $body, int $status): array
    {
        $request = new ServerRequestFactory()->createServerRequest($method, 'https://agent-os.test'.$path)
            ->withHeader('Authorization', 'Bearer '.$this->token)->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
        $response = $this->app->handle($request);
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $operationPath = $method === 'POST' ? '/api/v1/roles' : '/api/v1/roles/{roleId}';
        (new ValidatorBuilder())->fromJson(Document::generate())->getResponseValidator()->validate(
            new OperationAddress($operationPath, strtolower($method)),
            $response
        );

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Counts only committed definition audit records
     */
    private function roleAuditCount(): int
    {
        return (int) $this->connection->fetchOne("SELECT count(*) FROM audit_evidence WHERE subject_type = 'role'");
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
