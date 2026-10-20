<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Application\Failure\PermissionDenied;
use App\Application\Workspace\BootstrapWorkspace;
use App\Application\Workspace\GetRepositoryRegistration;
use App\Application\Workspace\RegistrationAccess;
use App\Application\Workspace\RegistrationFences;
use App\Application\Workspace\RegistrationStore;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Workspace\RegistrationIntent;
use App\Domain\Workspace\RegistrationMetadata;
use App\Domain\Workspace\RegistrationSources;
use App\Domain\Workspace\RepositoryId;
use App\Domain\Workspace\RepositoryRegistered;
use App\Domain\Workspace\RepositoryRegistration;
use App\Domain\Workspace\SourceDescriptor;
use App\Domain\Workspace\WorkspaceId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
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
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AccessClock;
use Tests\Support\AccessTokens;
use Tooling\OpenApi\Document;

/**
 * Exercises real registration routes, atomic owned evidence and current server authority on PostgreSQL
 */
final class RepositoryRegistrationTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private User $actor;
    private RefreshSession $session;
    private Role $operationRole;
    private WorkspaceId $workspace;
    private string $token;
    /** @var array<string, string|false> */
    private array $environment = [];
    /** @var list<array{bool, int}> */
    private array $events = [];
    private TestHandler $logs;
    private bool $dispatchThrows = false;
    private DateTimeImmutable $referenceTime;

    /**
     * Boots actual routes with explicit fixture grants rather than inferring new default memberships
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
            'TRUNCATE users, roles, permissions, workspaces, reference_generation_limits CASCADE'
        );
        foreach (['DATABASE_URL', 'APP_ACCESS_TOKEN_CONFIG'] as $name) {
            $this->environment[$name] = getenv($name);
        }
        $tokens = new AccessTokens();
        $tokens->clock->time = new DateTimeImmutable();
        $this->referenceTime = $tokens->clock->now();
        $now = $tokens->clock->now()->modify('-1 minute');
        putenv('DATABASE_URL='.$url);
        putenv('APP_ACCESS_TOKEN_CONFIG='.json_encode($tokens->configuration, JSON_THROW_ON_ERROR));
        $this->app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $this->container = $container;
        $container->get(CommandBus::class)->execute(new ReconcileManagedPolicy(InstallationManagedPolicy::create()));
        $permissionIds = [];
        foreach ([RegistrationAccess::VIEW, RegistrationAccess::REGISTER, RegistrationAccess::GENERATE] as $name) {
            $permissionIds[] = $container->get(PermissionRepository::class)
                ->getByName(PermissionName::fromString($name))->getId();
        }
        $this->operationRole = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_REGISTRATION_FIXTURE'),
            $permissionIds,
            $now
        );
        $container->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn() => $container->get(RoleRepository::class)->add($this->operationRole)
        );
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('registrar@example.test'), $now);
        $this->actor->activate(
            PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_BCRYPT)),
            $now
        );
        $this->actor->assignRole(RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID), $now);
        $this->actor->assignRole($this->operationRole->getId(), $now);
        $container->get(TransactionalUnitOfWork::class)->commitTransactional(
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
        $this->workspace = $container->get(BootstrapWorkspace::class)->bootstrap($this->actor->getId())->id;
        $this->logs = new TestHandler();
        $container->get('http.failure.logger')->setHandlers([$this->logs]);
    }

    /**
     * Removes only owned fault injection and restores invocation configuration
     */
    protected function tearDown(): void
    {
        foreach (['workspace_audit', 'workspace_operations'] as $table) {
            $this->connection->executeStatement(
                'ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS registration_fixture_abort'
            );
        }
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        $this->connection->close();
    }

    /**
     * Commits generated state and evidence before the event and returns an immutable retry result
     */
    public function testAtomicRegistrationAndOriginalResult(): void
    {
        $input = $this->input('alpha');
        $registered = $this->assertResponse($this->request('POST', '', $input), 201, 'register')['data'];
        self::assertSame($this->workspace->toString(), $registered['workspace_id']);
        self::assertSame($this->actor->getId()->toString(), $registered['created_by']);
        self::assertNull($registered['primary_source']);
        self::assertSame([[false, 1]], $this->events);
        $again = $this->assertResponse($this->request('POST', '', $input), 201, 'register')['data'];
        self::assertSame($registered, $again);
        self::assertCount(1, $this->events);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM repository_registrations'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM workspace_audit'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM workspace_operations'));
        $original = $this->connection->fetchOne(
            "SELECT original_result FROM workspace_operations WHERE command_kind = 'register_repository'"
        );
        self::assertSame($registered, json_decode($original, true, flags: JSON_THROW_ON_ERROR));
        self::assertSame($registered, $this->assertResponse(
            $this->request('GET', '/'.$registered['repository_id']),
            200,
            'get'
        )['data']);
        $input['display_name'] = 'Changed intent';
        $this->assertResponse($this->request('POST', '', $input), 409, 'register');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM repository_registrations'));
    }

    /**
     * Returns stable bootstrap identity and creates no additional audit on repeated explicit invocation
     */
    public function testIdempotentExplicitBootstrap(): void
    {
        $original = $this->container->get(BootstrapWorkspace::class)->bootstrap($this->actor->getId());
        $again = $this->container->get(BootstrapWorkspace::class)->bootstrap(UserId::generate());
        self::assertTrue($original->id->equals($again->id));
        self::assertEquals($original->createdAt, $again->createdAt);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM workspaces'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM workspace_audit'));
        self::assertSame('installation_operator', $this->connection->fetchOne(
            'SELECT actor_type FROM workspace_audit'
        ));
    }

    /**
     * Claims every explicitly approved source and applies the most specific supported profile
     */
    public function testSourcesConflictsPreviewAndOrdering(): void
    {
        $input = $this->input('alpha');
        $input['primary_source'] = ['type' => 'git_network_v1', 'locator' => 'https://GitHub.com/Team/Alpha.git'];
        $input['equivalent_sources'] = [
            ['type' => 'git_network_v1', 'locator' => 'ssh://git@mirror.example.test/Team/Alpha'],
            ['type' => 'git_network_v1', 'locator' => 'https://elsewhere.example.test/Team/Alpha.git']
        ];
        $alpha = $this->assertResponse($this->request('POST', '', $input), 201, 'register')['data'];
        self::assertSame([
            'type' => 'github_repository_v1', 'authority' => 'github.com', 'owner' => 'team', 'repository' => 'alpha'
        ], $alpha['primary_source']);
        self::assertCount(2, $alpha['equivalent_sources']);
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT count(*) FROM repository_sources WHERE active'));
        self::assertSame($alpha, $this->assertResponse(
            $this->request('GET', '/'.$alpha['repository_id']),
            200,
            'get'
        )['data']);
        $preview = $input;
        unset($preview['idempotency_key']);
        $before = $this->counts();
        self::assertSame(['slug', 'source'], $this->assertResponse(
            $this->request('POST', '/conflict-preview', $preview),
            200,
            'preview'
        )['data']['conflicts']);
        self::assertSame($before, $this->counts());
        self::assertCount(1, $this->events);
        $conflict = $this->input('different');
        $conflict['primary_source'] = ['type' => 'github_repository_v1', 'locator' => 'git@github.com:TEAM/ALPHA'];
        $this->assertResponse($this->request('POST', '', $conflict), 409, 'register');
        $conflict['primary_source'] = [
            'type' => 'git_network_v1', 'locator' => 'https://elsewhere.example.test/Team/Alpha.git'
        ];
        $this->assertResponse($this->request('POST', '', $conflict), 409, 'register');
        // A fork and a generic case/suffix difference remain separate collaboration evidence.
        $fork = $this->input('fork');
        $fork['primary_source'] = ['type' => 'git_network_v1', 'locator' => 'https://github.com/a-fork/alpha'];
        $this->assertResponse($this->request('POST', '', $fork), 201, 'register');
        $this->assertResponse($this->request('POST', '', $this->input('beta')), 201, 'register');
        $expected = $this->connection->fetchFirstColumn(
            'SELECT id FROM repository_registrations ORDER BY created_at, id'
        );
        $first = $this->assertResponse($this->request('GET', '?limit=2&offset=0'), 200, 'list')['data'];
        self::assertSame(array_slice($expected, 0, 2), array_column($first['items'], 'repository_id'));
        self::assertTrue($first['has_more']);
        $second = $this->assertResponse($this->request('GET', '?limit=2&offset=2'), 200, 'list')['data'];
        self::assertSame(array_slice($expected, 2), array_column($second['items'], 'repository_id'));
        self::assertFalse($second['has_more']);
        self::assertCount(3, $this->events);
    }

    /**
     * Rolls back all claims, state and original success when audit or result persistence fails
     */
    #[DataProvider('evidenceFailureTables')]
    public function testAtomicRollback(string $table): void
    {
        $before = $this->counts();
        $constraint = ' ADD CONSTRAINT registration_fixture_abort CHECK (repository_id IS NULL) NOT VALID';
        $this->connection->executeStatement('ALTER TABLE '.$table.$constraint);
        $input = $this->input('rolled-back');
        $input['primary_source'] = ['type' => 'git_network_v1', 'locator' => 'https://example.test/private-repo'];
        $this->assertResponse($this->request('POST', '', $input), 500, 'register');
        self::assertSame($before, $this->counts());
        self::assertSame([], $this->events);
        self::assertStringNotContainsString(
            'registration_fixture_abort',
            json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Supplies the two mandatory atomic evidence boundaries
     *
     * @return iterable<array{string}>
     */
    public static function evidenceFailureTables(): iterable
    {
        yield ['workspace_audit'];
        yield ['workspace_operations'];
    }

    /**
     * Refuses spoofed creation fields, unsafe sources and ambiguous nested input without echo or persistence
     *
     * @param array<string, mixed> $extra
     */
    #[DataProvider('invalidInputs')]
    public function testRejectedInput(array $extra, int $status): void
    {
        $before = $this->counts();
        $input = array_replace($this->input('invalid'), $extra);
        $response = $this->request('POST', '', $input);
        $this->assertResponse($response, $status, 'register');
        self::assertSame($before, $this->counts());
        self::assertSame([], $this->events);
        $output = (string) $response->getBody().json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('secret-marker', $output);
        self::assertStringNotContainsString('private-provider', $output);
    }

    /**
     * Supplies denied creation authority fields and closed nested representations
     *
     * @return iterable<string, array{array<string, mixed>, int}>
     */
    public static function invalidInputs(): iterable
    {
        foreach (
            [
            'repository_id', 'workspace_id', 'actor_id', 'revision', 'created_at', 'lifecycle', 'directory'
            ] as $field
        ) {
            yield $field => [[$field => 'secret-marker'], 400];
        }
        yield 'reserved slug' => [['slug' => 'api'], 422];
        yield 'coerced slug' => [['slug' => 'Project'], 422];
        yield 'invalid name' => [['display_name' => ' bad '], 422];
        yield 'credential locator' => [['primary_source' => [
            'type' => 'git_network_v1', 'locator' => 'https://user:secret-marker@private-provider.test/repo'
        ]], 422];
        yield 'local authority' => [['primary_source' => [
            'type' => 'git_local_v1', 'locator' => '/private-provider/secret-marker'
        ]], 422];
        yield 'provider authority' => [['primary_source' => [
            'type'        => 'github_repository_v1',
            'locator'     => 'https://github.com/team/repo',
            'provider_id' => 'secret-marker'
        ]], 422];
        yield 'unknown nested key' => [['primary_source' => [
            'type' => 'git_network_v1', 'locator' => 'https://example.test/a', 'secret-marker' => true
        ]], 422];
        yield 'scalar primary' => [['primary_source' => 'secret-marker'], 400];
        yield 'equivalents object' => [['equivalent_sources' => (object) ['private-provider' => 'secret-marker']], 400];
        yield 'equivalents scalar' => [['equivalent_sources' => ['secret-marker']], 422];
        yield 'equivalents without primary' => [['equivalent_sources' => [[
            'type' => 'git_network_v1', 'locator' => 'https://example.test/a'
        ]]], 422];
    }

    /**
     * Requires distinct current permissions and current Workspace access on every request and retry
     */
    public function testIndependentAuthorityAndScope(): void
    {
        $registered = $this->assertResponse($this->request('POST', '', $this->input('alpha')), 201, 'register')['data'];
        $this->revokeOperation(RegistrationAccess::VIEW);
        $this->assertResponse($this->request('GET'), 403, 'list');
        $this->assertResponse($this->request('GET', '/'.$registered['repository_id']), 403, 'get');
        $preview = $this->input('preview');
        unset($preview['idempotency_key']);
        $this->assertResponse($this->request('POST', '/conflict-preview', $preview), 403, 'preview');
        // Register is independent of view; only replaying an original result needs current disclosure authority.
        $this->assertResponse($this->request('POST', '', $this->input('beta')), 201, 'register');
        $this->assertResponse($this->request('POST', '', $this->input('alpha')), 403, 'register');
        $this->revokeOperation(RegistrationAccess::REGISTER);
        $this->assertResponse($this->request('POST', '', $this->input('gamma')), 403, 'register');
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM repository_registrations'));
    }

    /**
     * Rejects unknown Workspace claims and missing registrations without disclosing other scope
     */
    public function testUnknownScopeAndMissingRegistration(): void
    {
        $unknown = WorkspaceId::generate();
        $this->assertResponse($this->request('GET', '', null, $unknown), 403, 'list');
        $this->assertResponse($this->request('POST', '', $this->input('other-scope'), $unknown), 403, 'register');
        $this->assertResponse($this->request('GET', '/'.UserId::generate()->toString()), 404, 'get');
        $this->connection->executeStatement('TRUNCATE workspaces CASCADE');
        $this->assertResponse($this->request('GET'), 403, 'list');
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM workspaces'));
    }

    /**
     * Returns non-reserved references within an atomic admission budget and refuses authority-bearing input
     */
    public function testExceptionalReferences(): void
    {
        $before = $this->counts();
        $references = [];
        for ($index = 0; $index < 10; ++$index) {
            $data = $this->assertResponse($this->request('POST', '/references'), 200, 'reference')['data'];
            self::assertFalse($data['reserved']);
            self::assertSame($data['reference'], RepositoryId::fromString($data['reference'])->toString());
            $references[] = $data['reference'];
        }
        self::assertCount(10, array_unique($references));
        self::assertSame($before, $this->counts());
        $denied = $this->request('POST', '/references');
        $this->assertResponse($denied, 429, 'reference');
        self::assertGreaterThanOrEqual(1, (int) $denied->getHeaderLine('Retry-After'));
        self::assertSame(10, (int) $this->connection->fetchOne('SELECT attempts FROM reference_generation_limits'));
        $this->referenceTime = $this->referenceTime->modify('+1 minute');
        $this->assertResponse($this->request('POST', '/references'), 200, 'reference');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT attempts FROM reference_generation_limits'));
        $this->assertResponse($this->request('POST', '/references', ['timestamp' => 1]), 400, 'reference');
        $this->revokeOperation(RegistrationAccess::GENERATE);
        $this->assertResponse($this->request('POST', '/references'), 403, 'reference');
    }

    /**
     * Preserves committed success after post-commit dispatch failure and does not dispatch it again on retry
     */
    public function testLostPostCommitResponseRecoversOriginalResult(): void
    {
        $this->dispatchThrows = true;
        $input = $this->input('lost-response');
        $this->assertResponse($this->request('POST', '', $input), 500, 'register');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM repository_registrations'));
        $original = json_decode($this->connection->fetchOne(
            "SELECT original_result FROM workspace_operations WHERE command_kind = 'register_repository'"
        ), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($original, $this->assertResponse($this->request('POST', '', $input), 201, 'register')['data']);
        self::assertCount(1, $this->events);
    }

    /**
     * Chooses one committed claimant across two real simultaneous transactions
     */
    #[DataProvider('claimRaces')]
    public function testCompetingRegistrationClaims(string $kind): void
    {
        $first = $this->input('race-one');
        $second = $this->input($kind === 'slug' ? 'race-one' : 'race-two');
        if ($kind === 'source') {
            $first['primary_source'] = ['type' => 'git_network_v1', 'locator' => 'https://github.com/team/race.git'];
            $second['primary_source'] = ['type' => 'github_repository_v1', 'locator' => 'git@github.com:TEAM/RACE'];
        }
        if ($kind === 'idempotency') {
            $second = $first;
        } else {
            $second['idempotency_key'] = 'independent-race-two';
        }
        $children = [];
        try {
            $children[] = $this->startWorker($this->workerInput($first));
            $children[] = $this->startWorker($this->workerInput($second));
            foreach ($children as $child) {
                self::assertSame('ready', trim((string) fgets($child['pipes'][1])));
            }
            foreach ($children as $child) {
                $this->releaseWorker($child);
            }
            $results = [];
            foreach ($children as &$child) {
                $results[] = $this->finishWorker($child);
            }
            unset($child);
            $statuses = array_column($results, 'status');
            sort($statuses);
            self::assertSame(
                $kind === 'idempotency' ? ['registered', 'registered'] : ['conflict', 'registered'],
                $statuses
            );
            if ($kind === 'idempotency') {
                self::assertSame($results[0]['repository_id'], $results[1]['repository_id']);
            } else {
                self::assertSame([$kind], array_column($results, 'reason'));
            }
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM repository_registrations'));
            self::assertSame(1, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM workspace_operations WHERE event_name = 'RepositoryRegistered'"
            ));
            self::assertSame(1, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM workspace_audit WHERE action = 'repository_registered'"
            ));
            self::assertSame($kind === 'source' ? 1 : 0, (int) $this->connection->fetchOne(
                'SELECT count(*) FROM repository_sources'
            ));
        } finally {
            foreach ($children as $child) {
                $this->cleanWorker($child);
            }
        }
    }

    /**
     * Supplies independently contested canonical claims and exact retry identity
     *
     * @return iterable<array{string}>
     */
    public static function claimRaces(): iterable
    {
        yield ['slug'];
        yield ['source'];
        yield ['idempotency'];
    }

    /**
     * Serializes real permission, assignment and session revocation on both sides of protected admission
     */
    #[DataProvider('authorityRaces')]
    public function testCurrentAuthorityInterleavings(string $subject, bool $registrationFirst): void
    {
        $children = [];
        try {
            $register = $this->workerInput($this->input('authority-race'));
            $register['barrier'] = $registrationFirst ? 'after' : 'before';
            $children[] = $this->startWorker($register);
            self::assertSame($registrationFirst ? 'held' : 'ready', trim((string) fgets($children[0]['pipes'][1])));
            $revoke = $this->workerInput([]);
            $revoke['mode'] = 'revoke';
            $revoke['subject'] = $subject;
            $revoke['role'] = $this->operationRole->getId()->toString();
            $revoke['permission'] = RegistrationAccess::REGISTER;
            $children[] = $this->startWorker($revoke);
            self::assertSame('ready', trim((string) fgets($children[1]['pipes'][1])));
            $this->releaseWorker($children[1]);
            if ($registrationFirst) {
                // Observe PostgreSQL actually blocking the real revocation writer on the protected transaction.
                $deadline = microtime(true) + 5;
                do {
                    $blocked = (int) $this->connection->fetchOne(
                        'SELECT cardinality(pg_blocking_pids(?))',
                        [$children[1]['pid']]
                    );
                } while ($blocked === 0 && microtime(true) < $deadline);
                self::assertGreaterThan(0, $blocked, 'Revocation never reached the protected authority fence');
                $this->releaseWorker($children[0]);
                self::assertSame('registered', $this->finishWorker($children[0])['status']);
                self::assertSame('revoked', $this->finishWorker($children[1])['status']);
            } else {
                self::assertSame('revoked', $this->finishWorker($children[1])['status']);
                $this->releaseWorker($children[0]);
                self::assertSame('denied', $this->finishWorker($children[0])['status']);
            }
            self::assertSame($registrationFirst ? 1 : 0, (int) $this->connection->fetchOne(
                'SELECT count(*) FROM repository_registrations'
            ));
            self::assertSame($registrationFirst ? 1 : 0, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM workspace_audit WHERE action = 'repository_registered'"
            ));
        } finally {
            foreach ($children as $child) {
                $this->cleanWorker($child);
            }
        }
    }

    /**
     * Supplies both serial orders for each independent authority writer
     *
     * @return iterable<array{string, bool}>
     */
    public static function authorityRaces(): iterable
    {
        foreach (['permission', 'assignment', 'session'] as $subject) {
            yield [$subject, true];
            yield [$subject, false];
        }
    }

    /**
     * Gives two explicit bootstrap callers the same durable identity and original evidence
     */
    public function testConcurrentBootstrap(): void
    {
        $this->connection->executeStatement('TRUNCATE workspaces CASCADE');
        $children = [];
        try {
            for ($index = 0; $index < 2; ++$index) {
                $input = $this->workerInput([]);
                $input['mode'] = 'bootstrap';
                $children[] = $this->startWorker($input);
            }
            foreach ($children as $child) {
                self::assertSame('ready', trim((string) fgets($child['pipes'][1])));
            }
            foreach ($children as $child) {
                $this->releaseWorker($child);
            }
            $one = $this->finishWorker($children[0]);
            $two = $this->finishWorker($children[1]);
            self::assertSame('bootstrapped', $one['status']);
            self::assertSame($one, $two);
            self::assertSame([1, 0, 0, 1, 1], $this->counts());
        } finally {
            foreach ($children as $child) {
                $this->cleanWorker($child);
            }
        }
    }

    /**
     * Keeps operation permissions independent from initial administrator access
     */
    public function testPermissionAloneDoesNotGrantWorkspaceAccess(): void
    {
        $users = $this->container->get(UserRepository::class);
        $expected = $users->getById($this->actor->getId());
        self::assertInstanceOf(User::class, $expected);
        $replacement = clone $expected;
        $replacement->removeRole(
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID),
            new DateTimeImmutable()
        );
        self::assertTrue($this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn(): bool => $users->replaceRoleAssignments($expected, $replacement)
        ));
        $before = $this->counts();
        $this->assertResponse($this->request('POST', '', $this->input('denied')), 403, 'register');
        $this->assertResponse($this->request('GET'), 403, 'list');
        self::assertSame($before, $this->counts());
    }

    /**
     * Requires register independently of view while continuing to allow authorized metadata inspection
     */
    public function testViewDoesNotGrantRegister(): void
    {
        $this->revokeOperation(RegistrationAccess::REGISTER);
        $this->assertResponse($this->request('GET'), 200, 'list');
        $this->assertResponse($this->request('POST', '', $this->input('denied')), 403, 'register');
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM repository_registrations'));
    }

    /**
     * Translates only recognized final constraint conflicts and rolls back a losing partial claim set
     */
    #[DataProvider('finalConstraintConflicts')]
    public function testAuthoritativeConstraintTranslation(string $kind): void
    {
        $input = $this->input('owned-claim');
        $input['primary_source'] = ['type' => 'git_network_v1', 'locator' => 'https://example.test/owned-claim'];
        $stored = $this->assertResponse($this->request('POST', '', $input), 201, 'register')['data'];
        $before = $this->counts();
        $this->freshApplication();
        $metadata = new RegistrationMetadata('Contender', $kind === 'slug' ? 'owned-claim' : 'contender');
        $sources = new RegistrationSources(
            $kind === 'source' ? SourceDescriptor::fromInput($input['primary_source']) : null
        );
        $registration = RepositoryRegistration::register(
            $this->workspace,
            $metadata,
            $sources,
            $this->actor->getId(),
            $this->referenceTime
        );
        if ($kind === 'unknown') {
            $registration = RepositoryRegistration::reconstitute(
                RepositoryId::fromString($stored['repository_id']),
                $this->workspace,
                $metadata,
                $sources,
                $this->actor->getId(),
                $this->referenceTime
            );
        }
        $intent = new RegistrationIntent($metadata, $sources, 'constraint-contender');
        try {
            $this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
                fn() => $this->container->get(RegistrationStore::class)->add($registration, $intent)
            );
            self::fail('A final database claim conflict was accepted');
        } catch (\App\Domain\Workspace\RegistrationConflict $conflict) {
            self::assertSame($kind, $conflict->reason);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            self::assertSame('unknown', $kind, 'A known constraint was not safely translated');
        }
        self::assertSame($before, $this->counts());
    }

    /**
     * Supplies known named claim conflicts and an intentionally unclassified identity collision
     *
     * @return iterable<array{string}>
     */
    public static function finalConstraintConflicts(): iterable
    {
        yield ['slug'];
        yield ['source'];
        yield ['unknown'];
    }

    /**
     * Rejects anonymous, revoked and stale entry paths before any registration effect
     */
    public function testAuthenticationRefusals(): void
    {
        $before = $this->counts();
        $this->assertResponse($this->request('POST', '', $this->input('anonymous'), null, false), 401, 'register');
        $sessions = $this->container->get(RefreshSessionRepository::class);
        self::assertTrue($this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn(): bool => $sessions->replace($this->session, $this->session->revoke())
        ));
        $this->assertResponse($this->request('POST', '', $this->input('revoked')), 401, 'register');
        self::assertSame($before, $this->counts());
        $this->expectException(PermissionDenied::class);
        $this->container->get(GetRepositoryRegistration::class)->get(
            new AuthenticationContext(
                $this->actor->getId(),
                $this->session->getId(),
                $this->actor->getAuthenticationVersion()
            ),
            $this->workspace,
            RepositoryId::generate()
        );
    }

    /**
     * Refuses repeated nested keys before interpreting descriptors or persisting rejected bytes
     */
    public function testNestedDuplicateKeyRefusal(): void
    {
        $this->freshApplication();
        $before = $this->counts();
        $request = (new ServerRequestFactory())->createServerRequest(
            'POST',
            'https://localhost/api/v1/workspaces/'.$this->workspace->toString().'/repositories'
        )->withHeader('Authorization', 'Bearer '.$this->token)->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(<<<'JSON'
{
    "display_name": "Project", "slug": "project", "idempotency_key": "duplicate-0001",
    "primary_source": {
        "type": "git_network_v1",
        "locator": "https://example.test/a",
        "locator": "https://example.test/secret-marker"
    }
}
JSON);

        $this->assertResponse($this->app->handle($request), 400, 'register');
        self::assertSame($before, $this->counts());
    }

    /**
     * Returns fixture input with stable caller-bound intent
     *
     * @return array<string, mixed>
     */
    private function input(string $slug): array
    {
        return ['display_name' => 'Project '.$slug, 'slug' => $slug, 'idempotency_key' => 'register-'.$slug];
    }

    /**
     * Calls actual routes with the package-created session and current bearer authentication
     *
     * @phpstan-param array<string, mixed>|null $body
     */
    private function request(
        string $method,
        string $suffix = '',
        ?array $body = null,
        ?WorkspaceId $workspace = null,
        bool $authenticated = true
    ): ResponseInterface {
        $this->freshApplication();
        $path = '/api/v1/workspaces/'.($workspace ?? $this->workspace)->toString();
        $path .= $suffix === '/references' ? $suffix : '/repositories'.$suffix;
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://localhost'.$path);
        if ($authenticated) {
            $request = $request->withHeader('Authorization', 'Bearer '.$this->token);
        }
        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
        }

        return $this->app->handle($request);
    }

    /**
     * Checks real responses against OpenAPI and transport/redaction guarantees
     *
     * @return array<string, mixed>
     */
    private function assertResponse(ResponseInterface $response, int $status, string $operation): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $response->getHeaderLine('X-Correlation-ID'));
        self::assertFalse($response->hasHeader('Set-Cookie'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        static $json = null;
        $json ??= Document::generate();
        [$path, $method] = match ($operation) {
            'register' => ['/repositories', 'post'], 'get' => ['/repositories/{repository_id}', 'get'],
            'list' => ['/repositories', 'get'], 'preview' => ['/repositories/conflict-preview', 'post'],
            'reference' => ['/references', 'post'],
            default => throw new \LogicException('Unknown test operation')
        };
        (new ValidatorBuilder())->fromJson($json)->getResponseValidator()->validate(
            new OperationAddress('/api/v1/workspaces/{workspace_id}'.$path, $method),
            $response
        );
        $body = (string) $response->getBody();
        foreach (
            [
            'secret-marker', 'private-provider', 'SQLSTATE', 'uq_workspace', 'password_hash', 'credential_digest'
            ] as $secret
        ) {
            self::assertStringNotContainsString($secret, $body);
        }

        return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Counts only authoritative registration effects, excluding independently bounded limiter metadata
     *
     * @return list<int>
     */
    private function counts(): array
    {
        return array_map(
            fn(string $table): int => (int) $this->connection->fetchOne('SELECT count(*) FROM '.$table),
            ['workspaces', 'repository_registrations', 'repository_sources', 'workspace_audit', 'workspace_operations']
        );
    }

    /**
     * Maps current fixture authority into an independently executed operation
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function workerInput(array $body): array
    {
        return [
            'mode'      => 'register',
            'body'      => $body,
            'workspace' => $this->workspace->toString(),
            'actor'     => $this->actor->getId()->toString(),
            'session'   => $this->session->getId()->toString(),
            'version'   => $this->actor->getAuthenticationVersion()
        ];
    }

    /**
     * Starts a bounded separate-connection operation behind a parent-controlled protocol barrier
     *
     * @param array<string, mixed> $input
     *
     * @return array{process: resource, pipes: array<int, resource>, pid: int, finished: bool}
     */
    private function startWorker(array $input): array
    {
        $pipes = [];
        $process = proc_open([
            PHP_BINARY, '-r',
            'require "vendor/autoload.php"; Tests\\Support\\RepositoryRegistrationRaceWorker::run();'
        ], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
        self::assertIsResource($process);
        stream_set_timeout($pipes[1], 20);
        fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR)."\n");
        fflush($pipes[0]);
        $pid = (int) fgets($pipes[1]);
        self::assertGreaterThan(0, $pid);

        return ['process' => $process, 'pipes' => $pipes, 'pid' => $pid, 'finished' => false];
    }

    /**
     * Releases one explicitly observed worker barrier
     *
     * @param array{process: resource, pipes: array<int, resource>, pid: int, finished: bool} $worker
     */
    private function releaseWorker(array $worker): void
    {
        fwrite($worker['pipes'][0], "go\n");
        fflush($worker['pipes'][0]);
    }

    /**
     * Collects a safe result and confirms successful child completion
     *
     * @param array{process: resource, pipes: array<int, resource>, pid: int, finished: bool} $worker
     *
     * @return array<string, mixed>
     */
    private function finishWorker(array &$worker): array
    {
        $result = json_decode((string) fgets($worker['pipes'][1]), true, flags: JSON_THROW_ON_ERROR);
        foreach ($worker['pipes'] as $pipe) {
            fclose($pipe);
        }
        $exit = proc_close($worker['process']);
        $worker['finished'] = true;
        self::assertSame(0, $exit);

        return $result;
    }

    /**
     * Stops only an unfinished owned child when an assertion interrupts a controlled race
     *
     * @param array{process: resource, pipes: array<int, resource>, pid: int, finished: bool} $worker
     */
    private function cleanWorker(array $worker): void
    {
        if (!$worker['finished']) {
            proc_terminate($worker['process']);
            foreach ($worker['pipes'] as $pipe) {
                fclose($pipe);
            }
            proc_close($worker['process']);
        }
    }

    /**
     * Models the actual per-request PHP application lifetime after a transaction failure closes its manager
     */
    private function freshApplication(): void
    {
        $this->app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $this->container = $container;
        $time = $this->referenceTime;
        $container->set(Clock::class, static fn() => new AccessClock($time));
        $container->get('http.failure.logger')->setHandlers([$this->logs]);
        $container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(RepositoryRegistered::class),
            function (): void {
                $this->events[] = [
                    $this->container->get(Connection::class)->isTransactionActive(),
                    (int) $this->connection->fetchOne(
                        "SELECT count(*) FROM workspace_operations WHERE event_name = 'RepositoryRegistered'"
                    )
                ];
                if ($this->dispatchThrows) {
                    throw new RuntimeException('private-provider secret-marker');
                }
            }
        );
    }

    /**
     * Uses the real permission-membership writer to change current authority
     */
    private function revokeOperation(string $name): void
    {
        $this->freshApplication();
        $roles = $this->container->get(RoleRepository::class);
        $expected = $roles->getById($this->operationRole->getId());
        $permission = $this->container->get(PermissionRepository::class)->getByName(PermissionName::fromString($name));
        $replacement = $expected->revokePermissionFromCustom($permission->getId(), new DateTimeImmutable());
        self::assertTrue($this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn(): bool => $roles->replace($expected, $replacement)
        ));
    }
}
