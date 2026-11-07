<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Action\Api\V1\Users\GetUserAction;
use App\Adapter\Http\Action\Api\V1\Users\ListUsersAction;
use App\Adapter\Http\Responder\Api\V1\Users\UserDetailResponder;
use App\Adapter\Http\Responder\Api\V1\Users\UserDirectoryResponder;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Adapter\Security\AccessToken\AccessJwt;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use App\Domain\Authorization\InstallationManagedPolicy;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Messaging\Query\Query;
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
use Tests\Support\AccessTokens;
use Tests\Support\CatalogQueryProbe;
use Tooling\OpenApi\Document;

/**
 * Exercises the actual directory through Bearer, current authority, validation, bus and PostgreSQL
 */
final class UserDirectoryApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private User $actor;
    private RefreshSession $session;
    private string $token;
    private AccessTokens $tokens;
    private CatalogQueryProbe $probe;
    /** @var array<string, string|false> */
    private array $environment = [];

    /**
     * Boots real routes against only the guarded task database
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
        $tokens->clock->time = new DateTimeImmutable('2026-10-26T12:00:00Z');
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
        $now = $tokens->clock->now()->modify('-1 minute');
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('directory@example.test'), $now);
        $this->actor->activate(
            PasswordHash::fromString((string) password_hash('private-password-marker', PASSWORD_BCRYPT, ['cost' => 4])),
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
     * Restores caller environment and leaves unrelated runtimes untouched
     */
    protected function tearDown(): void
    {
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        $this->connection->close();
    }

    /**
     * Requires only exact READ_USERS independently of every invitation permission combination
     */
    #[DataProvider('permissionCombinations')]
    public function testPermissionMatrix(int $mask): void
    {
        $this->check($this->request('', false), 401);
        self::assertSame(0, $this->probe->count());
        $names = ['READ_USERS', 'INVITE_USERS', 'MANAGE_USER_INVITATIONS', 'ASSIGN_SUPER_ADMIN'];
        $allowed = [];
        foreach ($names as $index => $name) {
            if ($mask & (1 << $index)) {
                $allowed[] = $name;
            }
        }
        $this->connection->executeStatement(
            'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name NOT IN (?))',
            [$allowed === [] ? ['NO_PERMISSION'] : $allowed],
            [ArrayParameterType::STRING]
        );
        $this->check($this->request(), ($mask & 1) ? 200 : 403);
        self::assertSame(($mask & 1) ? 2 : 0, $this->probe->count());
    }

    /**
     * Supplies every exact read/invitation/elevation permission subset
     *
     * @return iterable<array{int}>
     */
    public static function permissionCombinations(): iterable
    {
        foreach (range(0, 15) as $mask) {
            yield [$mask];
        }
    }

    /**
     * Refuses signed Role and permission claims as authentication rather than granting their assertion
     */
    public function testTokenClaimsCannotGrantDirectoryAccess(): void
    {
        $claims = $this->tokens->claims();
        $claims['sub'] = $this->actor->getId()->toString();
        $claims['sid'] = $this->session->getId()->toString();
        $claims['roles'] = ['ROLE_SUPER_ADMIN'];
        $claims['permissions'] = ['READ_USERS'];
        $this->token = $this->tokens->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        $this->check($this->request(), 401);
        $this->check($this->request(target: '/'.$this->actor->getId()->toString()), 401, true);
        self::assertSame(0, $this->probe->count());
    }

    /**
     * Returns canonical safe identities of every lifecycle with tied deterministic pages and exact roles
     */
    public function testSafeDirectory(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $custom = Role::define(RoleId::generate(), RoleName::fromString('ROLE_CUSTOM'), [], $now);
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(RoleRepository::class)->add($custom)
        );
        foreach (['pending_activation', 'active', 'disabled', 'deleted'] as $index => $state) {
            $user = User::invite(UserId::generate(), EmailAddress::fromString('PERSON'.$index.'@EXAMPLE.TEST'), $now);
            if ($state !== 'pending_activation') {
                $user->activate($this->actor->getPasswordHash(), $now);
            }
            $user->assignRole($custom->getId(), $now);
            $user->assignRole(RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID), $now);
            $this->container->get(Connection::class)->transactional(
                fn() => $this->container->get(UserRepository::class)->add($user)
            );
            // Deliberate persisted states exercise directory visibility, not lifecycle mutation behavior.
            $this->connection->executeStatement(
                'UPDATE users SET state = ? WHERE id = ?',
                [$state, $user->getId()->toString()]
            );
        }
        $this->connection->executeStatement(
            'UPDATE users SET pending_email_change = ? WHERE id = ?',
            ['private-reservation-marker@example.test', $this->actor->getId()->toString()]
        );
        $expected = $this->connection->fetchFirstColumn('SELECT id FROM users ORDER BY created_at, id');
        $ids = [];
        $states = [];
        foreach (range(1, count($expected)) as $page) {
            $data = $this->check($this->request('?page='.$page.'&per_page=1'), 200)['data'];
            self::assertSame($page, $data['page']);
            self::assertSame(count($expected), $data['total_records']);
            self::assertCount(1, $data['records']);
            $record = $data['records'][0];
            self::assertSame(['user_id', 'email', 'state', 'roles', 'created_at', 'updated_at'], array_keys($record));
            self::assertSame(strtolower($record['email']), $record['email']);
            $ids[] = $record['user_id'];
            $states[] = $record['state'];
            if ($record['user_id'] !== $this->actor->getId()->toString()) {
                self::assertSame([
                    ['name' => 'ROLE_CUSTOM', 'managed' => false],
                    ['name' => 'ROLE_USER', 'managed' => true]
                ], $record['roles']);
            }
            self::assertSame($data, $this->check($this->request('?page='.$page.'&per_page=1'), 200)['data']);
        }
        self::assertSame($expected, $ids);
        self::assertContains('pending_activation', $states);
        self::assertContains('active', $states);
        self::assertContains('disabled', $states);
        self::assertContains('deleted', $states);
        $empty = $this->check($this->request('?page=1000&per_page=100'), 200)['data'];
        self::assertSame([], $empty['records']);
        self::assertSame(1000, $empty['page']);
        $encoded = $this->check($this->request('?%70age=%31&per%5Fpage=001'), 200)['data'];
        self::assertSame(1, $encoded['page']);
        self::assertSame(1, $encoded['per_page']);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    /**
     * Preserves package-accepted quoted canonical identity for another directory user (R59-01)
     */
    public function testQuotedCanonicalEmail(): void
    {
        $user = User::invite(
            UserId::generate(),
            EmailAddress::fromString('"a@b"@EXAMPLE.TEST'),
            new DateTimeImmutable('2026-01-01T00:00:00Z')
        );
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(UserRepository::class)->add($user)
        );
        $records = $this->check($this->request(), 200)['data']['records'];
        self::assertCount(2, $records);
        self::assertSame($user->getId()->toString(), $records[0]['user_id']);
        self::assertSame('"a@b"@example.test', $records[0]['email']);
        $detail = $this->check($this->request(target: '/'.$user->getId()->toString()), 200, true)['data'];
        self::assertSame('"a@b"@example.test', $detail['email']);
        self::assertSame([], $detail['roles']);
        self::assertSame($this->actor->getId()->toString(), $records[1]['user_id']);
    }

    /**
     * Rejects all undeclared, ambiguous and malformed input before package dispatch
     */
    #[DataProvider('badInput')]
    public function testValidation(string $query, string $body = ''): void
    {
        $this->check($this->request($query, true, $body), 400);
        self::assertSame(0, $this->probe->count());
    }

    /**
     * Supplies query bounds, encoding, collision, container and selector attacks
     *
     * @return iterable<array{string, string}>
     */
    public static function badInput(): iterable
    {
        $queries = [
            '?page=0', '?page=1001', '?per_page=0', '?per_page=101', '?page=-1', '?page=1.5', '?page=1e2',
            '?page=', '?page=false', '?page=%GG', '?page=%00', '?page=1&%70age=2', '?page[]=1',
            '?per_page=1&per%5Fpage=2', '?per.page=1', '?page=%201', '?page=1%20',
            '?sort=email', '?order=id%20DESC', '?include_deleted=false', '?filter[email]=person',
            '?actor_id=private-marker', '?permission=READ_USERS', '?scope=all', '?workspace_id=private-marker',
            '?roles=ROLE_SUPER_ADMIN', '?include=password_hash', '?page='.str_repeat('1', 8193)
        ];
        foreach ($queries as $query) {
            yield [$query, ''];
        }
        yield ['', '{"actor_id":"private-body-marker"}'];
        yield ['?page=1', '{"page":2}'];
    }

    /**
     * Refreshes central authority on each request without dispatch after revocation or principal corruption
     */
    #[DataProvider('authorityFailures')]
    public function testStaleAuthority(string $fault): void
    {
        $this->check($this->request(), 200);
        $this->check($this->request(target: '/'.$this->actor->getId()->toString()), 200, true);
        $dispatched = $this->probe->count();
        match ($fault) {
            'assignment' => $this->connection->executeStatement('DELETE FROM user_role_assignments'),
            'permission' => $this->connection->executeStatement('DELETE FROM role_permissions'),
            'session' => $this->connection->executeStatement('UPDATE refresh_sessions SET revoked = TRUE'),
            'revision' => $this->connection->executeStatement(
                'UPDATE users SET authentication_version = authentication_version + 1'
            ),
            'spoof' => $this->connection->executeStatement(
                "UPDATE roles SET managed = FALSE WHERE name = 'ROLE_SUPER_ADMIN'"
            ),
            'pending_activation' => $this->connection->executeStatement(
                "UPDATE users SET state = 'pending_activation', password_hash = NULL"
            ),
            default => $this->connection->executeStatement('UPDATE users SET state = ?', [$fault])
        };
        $status = in_array($fault, ['assignment', 'permission'], true) ? 403 : 401;
        $this->check($this->request(), $status);
        $this->check($this->request(target: '/'.$this->actor->getId()->toString()), $status, true);
        self::assertSame($dispatched, $this->probe->count());
    }

    /**
     * Supplies independently stale principal and authority states
     *
     * @return iterable<array{string}>
     */
    public static function authorityFailures(): iterable
    {
        $faults = [
            'assignment', 'permission', 'session', 'revision', 'spoof', 'pending_activation', 'disabled', 'deleted'
        ];
        foreach ($faults as $fault) {
            yield [$fault];
        }
    }

    /**
     * Fails closed and logs only correlation metadata when target role resolution becomes unavailable
     */
    public function testRoleResolutionFailure(): void
    {
        $logs = new TestHandler();
        $this->container->get('http.failure.logger')->setHandlers([$logs]);
        $this->container->set(ListUsersAction::class, function (Container $c): ListUsersAction {
            $bus = $c->get(QueryBus::class);
            $queries = $this->createStub(QueryBus::class);
            $queries->method('fetch')->willReturnCallback(static function (Query $query) use ($bus): mixed {
                if ($query instanceof GetRoleById) {
                    throw new \RuntimeException('private-SQL-provider-marker');
                }

                return $bus->fetch($query);
            });

            return new ListUsersAction(
                $queries,
                $c->get(UserDirectoryResponder::class)
            );
        });
        self::assertSame(
            ['status' => 'error', 'message' => 'Internal server error.'],
            $this->check($this->request(), 500)
        );
        self::assertSame(1, $this->probe->count());
        self::assertCount(1, $logs->getRecords());
        $record = $logs->getRecords()[0];
        self::assertSame('HTTP request failed.', $record->message);
        self::assertSame(
            ['http_status', 'exception_type'],
            array_keys($record->context)
        );
        self::assertStringNotContainsString(
            'private-SQL-provider-marker',
            json_encode($record->context, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Requires exact READ_USERS before any known, unknown or own-target detail lookup
     */
    #[DataProvider('permissionCombinations')]
    public function testDetailPermissionMatrix(int $mask): void
    {
        $target = '/'.$this->actor->getId()->toString();
        $this->check($this->request(target: $target, authenticated: false), 401, true);
        self::assertSame(0, $this->probe->count());
        $names = ['READ_USERS', 'INVITE_USERS', 'MANAGE_USER_INVITATIONS', 'ASSIGN_SUPER_ADMIN'];
        $allowed = ['READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS'];
        foreach ($names as $index => $name) {
            if ($mask & (1 << $index)) {
                $allowed[] = $name;
            }
        }
        $this->connection->executeStatement(
            'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name NOT IN (?))',
            [$allowed],
            [ArrayParameterType::STRING]
        );
        $this->check($this->request(target: $target), ($mask & 1) ? 200 : 403, true);
        $this->check($this->request(target: '/'.UserId::generate()->toString()), ($mask & 1) ? 404 : 403, true);
        $this->check($this->request(target: '/private-invalid-target'), ($mask & 1) ? 400 : 403, true);
        self::assertSame(($mask & 1) ? 3 : 0, $this->probe->count());
    }

    /**
     * Returns every lifecycle through the package and shared exact role and field allowlists
     */
    public function testSafeDetail(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $custom = Role::define(RoleId::generate(), RoleName::fromString('ROLE_CUSTOM'), [], $now);
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(RoleRepository::class)->add($custom)
        );
        foreach (['pending_activation', 'active', 'disabled', 'deleted'] as $index => $state) {
            $user = User::invite(UserId::generate(), EmailAddress::fromString('PERSON'.$index.'@EXAMPLE.TEST'), $now);
            if ($state !== 'pending_activation') {
                $user->activate($this->actor->getPasswordHash(), $now);
            }
            $user->assignRole($custom->getId(), $now);
            $user->assignRole(RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID), $now);
            $this->container->get(Connection::class)->transactional(
                fn() => $this->container->get(UserRepository::class)->add($user)
            );
            $this->connection->executeStatement(
                'UPDATE users SET state = ?, pending_email_change = ? WHERE id = ?',
                [$state, 'private-reservation@example.test', $user->getId()->toString()]
            );
            $target = '/'.$user->getId()->toString();
            $data = $this->check($this->request(target: $target), 200, true)['data'];
            self::assertSame(['user_id', 'email', 'state', 'roles', 'created_at', 'updated_at'], array_keys($data));
            self::assertSame($state, $data['state']);
            self::assertSame('person'.$index.'@example.test', $data['email']);
            self::assertSame($user->getId()->toString(), $data['user_id']);
            self::assertSame([
                ['name' => 'ROLE_CUSTOM', 'managed' => false],
                ['name' => 'ROLE_USER', 'managed' => true]
            ], $data['roles']);
            self::assertSame($data, $this->check($this->request(target: $target), 200, true)['data']);
        }
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    /**
     * Rejects malformed paths, query selectors and bodies without dispatch
     */
    #[DataProvider('detailInput')]
    public function testDetailValidation(string $target, string $query, string $body): void
    {
        $target = $target === 'valid' ? $this->actor->getId()->toString() : $target;
        $this->check($this->request($query, true, $body, '/'.$target), 400, true);
        self::assertSame(0, $this->probe->count());
    }

    /**
     * Supplies bounded path and undeclared input attacks
     *
     * @return iterable<array{string, string, string}>
     */
    public static function detailInput(): iterable
    {
        foreach (['not-a-uuid', str_repeat('a', 37), str_repeat('a', 36)] as $id) {
            yield [$id, '', ''];
        }
        $queries = ['?include=password_hash', '?actor_id=private-marker', '?page=1', '?roles=ROLE_SUPER_ADMIN'];
        foreach ($queries as $query) {
            yield ['valid', $query, ''];
        }
        yield ['valid', '', '{"permission":"READ_USERS"}'];
    }

    /**
     * Reports a target removed after the early permission gate as an authorized not-found
     */
    public function testDetailTargetDisappears(): void
    {
        $user = User::invite(
            UserId::generate(),
            EmailAddress::fromString('disappearing@example.test'),
            $this->tokens->clock->now()
        );
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(UserRepository::class)->add($user)
        );
        $this->container->set(GetUserAction::class, function (Container $c) use ($user): GetUserAction {
            $this->connection->executeStatement('DELETE FROM users WHERE id = ?', [$user->getId()->toString()]);

            return new GetUserAction(
                $c->get(QueryBus::class),
                $c->get(UserDetailResponder::class)
            );
        });
        $this->check($this->request(target: '/'.$user->getId()->toString()), 404, true);
        self::assertSame(1, $this->probe->count());
    }

    /**
     * Rejects an unmanaged reserved-name impersonation at the central principal gate
     */
    public function testDetailRoleNameSpoof(): void
    {
        $this->connection->executeStatement(
            "UPDATE roles SET managed = FALSE WHERE name = 'ROLE_SUPER_ADMIN'"
        );
        $this->check($this->request(target: '/'.$this->actor->getId()->toString()), 401, true);
        self::assertSame(0, $this->probe->count());
    }

    /**
     * Refuses a lookalike role label without the exact authoritative permission on both reads
     */
    public function testRoleLabelCannotGrantAccess(): void
    {
        $this->connection->executeStatement('DELETE FROM role_permissions');
        $this->connection->executeStatement(
            "UPDATE roles SET name = 'ROLE_LOOKALIKE' WHERE name = 'ROLE_SUPER_ADMIN'"
        );
        $this->check($this->request(), 403);
        $this->check($this->request(target: '/'.$this->actor->getId()->toString()), 403, true);
        self::assertSame(0, $this->probe->count());
    }

    /**
     * Fails closed when a persisted target reference disappears without exposing private diagnostics
     */
    public function testDetailMissingRole(): void
    {
        $now = $this->tokens->clock->now();
        $role = Role::define(RoleId::generate(), RoleName::fromString('ROLE_TARGET'), [], $now);
        $user = User::invite(UserId::generate(), EmailAddress::fromString('target@example.test'), $now);
        $user->assignRole($role->getId(), $now);
        $this->container->get(Connection::class)->transactional(function () use ($role, $user): void {
            $this->container->get(RoleRepository::class)->add($role);
            $this->container->get(UserRepository::class)->add($user);
        });
        $this->container->set(GetUserAction::class, function (Container $c): GetUserAction {
            $bus = $c->get(QueryBus::class);
            $queries = $this->createStub(QueryBus::class);
            $queries->method('fetch')->willReturnCallback(static fn(Query $query): mixed =>
                $query instanceof GetRoleById ? null : $bus->fetch($query));

            return new GetUserAction(
                $queries,
                $c->get(UserDetailResponder::class)
            );
        });
        $this->check($this->request(target: '/'.$user->getId()->toString()), 500, true);
        self::assertSame(1, $this->probe->count());
    }

    /**
     * Sends actual raw input through the registered guarded route
     */
    private function request(
        string $query = '',
        bool $authenticated = true,
        string $body = '',
        string $target = ''
    ): ResponseInterface {
        $path = '/api/v1/users'.$target.$query;
        $request = new ServerRequestFactory()->createServerRequest(
            'GET',
            'https://agent-os.test'.$path,
            ['REQUEST_URI' => $path]
        );
        if ($authenticated) {
            $request = $request->withHeader('Authorization', 'Bearer '.$this->token);
        }
        $request->getBody()->write($body);

        return $this->app->handle($request);
    }

    /**
     * Checks the real wire contract, caching and seeded sensitive-field exclusion
     *
     * @return array<string, mixed>
     */
    private function check(ResponseInterface $response, int $status, bool $detail = false): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $response->getHeaderLine('X-Correlation-ID'));
        self::assertFalse($response->hasHeader('Set-Cookie'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        $body = (string) $response->getBody();
        $privateValues = [
            $this->token, $this->session->getId()->toString(), $this->session->getCredentialDigest(),
            $this->actor->getPasswordHash()->toString(), 'private-', 'password_hash', 'authentication_version',
            'revision', 'permission_ids', 'role_ids', 'pending_email', 'ciphertext', 'grant', 'provider', 'audit'
        ];
        foreach ($privateValues as $private) {
            self::assertStringNotContainsString($private, $body);
        }
        static $spec = null;
        $spec ??= Document::generate();
        (new ValidatorBuilder())->fromJson($spec)->getResponseValidator()->validate(
            new OperationAddress($detail ? '/api/v1/users/{userId}' : '/api/v1/users', 'get'),
            $response
        );

        return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }
}
