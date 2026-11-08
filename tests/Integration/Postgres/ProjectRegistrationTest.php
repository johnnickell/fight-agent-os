<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Console\BootstrapWorkspaceCommand;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Workspace\Project\Event\ProjectRegistered;
use App\Domain\Workspace\Project\Project;
use App\Domain\Workspace\Project\ProjectConflict;
use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\ProjectMetadata;
use App\Domain\Workspace\Project\ProjectRepository;
use App\Domain\Workspace\Project\ProjectSources;
use App\Domain\Workspace\Project\SourceDescriptor;
use App\Domain\Workspace\Workspace\Command\BootstrapWorkspace;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
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
use Fight\Common\Application\Messaging\Query\QueryBus;
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
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\AccessTokens;
use Tests\Support\WorkspaceTestClock;
use Tooling\OpenApi\Document;

/**
 * Exercises actual Project HTTP admission, configured CQRS, atomic claims/audit and current-state recovery
 */
final class ProjectRegistrationTest extends TestCase
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
    private DateTimeImmutable $time;

    /**
     * Boots actual routes with independent explicit fixture grants
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
        $tokens->clock->time = new DateTimeImmutable('2026-11-08T05:00:00Z');
        $this->time = $tokens->clock->now();
        $now = $this->time->modify('-1 minute');
        putenv('DATABASE_URL='.$url);
        putenv('APP_ACCESS_TOKEN_CONFIG='.json_encode($tokens->configuration, JSON_THROW_ON_ERROR));
        $this->logs = new TestHandler();
        $this->freshApplication();
        $c = $this->container;
        $c->get(CommandBus::class)->execute(new ReconcileManagedPolicy(InstallationManagedPolicy::create()));
        $permissionIds = [];
        foreach (['READ_PROJECTS', 'REGISTER_PROJECTS', 'GENERATE_REFERENCE_IDS'] as $name) {
            $permissionIds[] = $c->get(PermissionRepository::class)
                ->getByName(PermissionName::fromString($name))->getId();
        }
        $this->operationRole = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_PROJECT_FIXTURE'),
            $permissionIds,
            $now
        );
        $c->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn() => $c->get(RoleRepository::class)->add($this->operationRole)
        );
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('registrar@example.test'), $now);
        $this->actor->activate(
            PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_BCRYPT)),
            $now
        );
        $this->actor->assignRole(RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID), $now);
        $this->actor->assignRole($this->operationRole->getId(), $now);
        $c->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn() => $c->get(UserRepository::class)->add($this->actor)
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
        $this->workspace = WorkspaceId::generate();
        $c->get(CommandBus::class)->execute(new BootstrapWorkspace($this->workspace, $this->actor->getId()));
    }

    /**
     * Removes only owned fault injection and restores configuration
     */
    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE workspace_audit DROP CONSTRAINT IF EXISTS project_fixture_abort'
        );
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        $this->connection->close();
    }

    /**
     * Creates atomic state and audit then reconciles a lost response through fresh independent reads
     */
    public function testCreationAndFreshQueryRecovery(): void
    {
        $input = $this->input('alpha');
        $created = $this->assertResponse($this->request('POST', '', $input), 201, 'register')['data'];
        self::assertSame(['project_id'], array_keys($created));
        $id = $created['project_id'];
        $view = $this->assertResponse($this->request('GET', '/'.$id), 200, 'get')['data'];
        self::assertSame($this->workspace->toString(), $view['workspace_id']);
        self::assertSame($this->actor->getId()->toString(), $view['created_by']);
        self::assertSame('Project alpha', $view['display_name']);
        self::assertSame([[false, 1]], $this->events);
        $before = $this->counts();
        $conflict = $this->request('POST', '', $input);
        $this->assertResponse($conflict, 409, 'register');
        self::assertStringNotContainsString($id, (string) $conflict->getBody());
        $page = $this->assertResponse($this->request('GET'), 200, 'list')['data'];
        self::assertSame([$view], $page['items']);
        self::assertSame($before, $this->counts());
        self::assertCount(1, $this->events);
        self::assertSame([1, 1, 0, 2, 0], $before);
    }

    /**
     * Preserves committed state after post-commit failure without replaying the original response
     */
    public function testPostCommitDispatchUncertainty(): void
    {
        $this->dispatchThrows = true;
        $input = $this->input('lost-response');
        $this->assertResponse($this->request('POST', '', $input), 500, 'register');
        self::assertSame([1, 1, 0, 2, 0], $this->counts());
        $this->assertResponse($this->request('POST', '', $input), 409, 'register');
        $page = $this->assertResponse($this->request('GET'), 200, 'list')['data'];
        self::assertSame('lost-response', $page['items'][0]['slug']);
        $this->assertResponse($this->request('GET', '/'.$page['items'][0]['project_id']), 200, 'get');
        self::assertCount(1, $this->events);
    }

    /**
     * Rolls back the complete Project and source set when required audit fails
     */
    public function testRequiredAuditRollback(): void
    {
        $before = $this->counts();
        $this->connection->executeStatement(<<<'SQL'
ALTER TABLE workspace_audit ADD CONSTRAINT project_fixture_abort CHECK (project_id IS NULL) NOT VALID
SQL);
        $input = $this->input('rollback');
        $input['primary_source'] = ['type' => 'git_network_v1', 'locator' => 'https://example.test/private-project'];
        $this->assertResponse($this->request('POST', '', $input), 500, 'register');
        self::assertSame($before, $this->counts());
        self::assertSame([], $this->events);
        self::assertStringNotContainsString(
            'project_fixture_abort',
            json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Preserves canonical equivalence, generic distinctions, fork identities and bounded deterministic pages
     */
    public function testSourcesPreviewAndPagination(): void
    {
        $input = $this->input('alpha');
        $input['primary_source'] = ['type' => 'git_network_v1', 'locator' => 'https://GitHub.com/Team/Alpha.git'];
        $input['equivalent_sources'] = [['type' => 'git_network_v1', 'locator' => 'git@mirror.test:Team/Alpha']];
        $id = $this->assertResponse($this->request('POST', '', $input), 201, 'register')['data']['project_id'];
        $alpha = $this->assertResponse($this->request('GET', '/'.$id), 200, 'get')['data'];
        self::assertSame([
            'type' => 'github_repository_v1', 'authority' => 'github.com', 'owner' => 'team', 'repository' => 'alpha'
        ], $alpha['primary_source']);
        self::assertCount(1, $alpha['equivalent_sources']);
        $before = $this->counts();
        self::assertSame(['slug', 'source'], $this->assertResponse(
            $this->request('POST', '/conflict-preview', $input),
            200,
            'preview'
        )['data']['conflicts']);
        self::assertSame($before, $this->counts());
        foreach (['git@github.com:TEAM/ALPHA', 'git@mirror.test:Team/Alpha'] as $locator) {
            $contender = $this->input('contender');
            $contender['primary_source'] = ['type' => 'git_network_v1', 'locator' => $locator];
            $this->assertResponse($this->request('POST', '', $contender), 409, 'register');
        }
        foreach (
            [
            'fork'             => 'https://github.com/a-fork/alpha',
            'generic-case'     => 'https://mirror.test/team/Alpha',
            'generic-absolute' => 'ssh://git@mirror.test/Team/Alpha',
            'generic-suffix'   => 'git@mirror.test:Team/Alpha.git'
            ] as $slug => $locator
        ) {
            $other = $this->input($slug);
            $other['primary_source'] = ['type' => 'git_network_v1', 'locator' => $locator];
            $this->assertResponse($this->request('POST', '', $other), 201, 'register');
        }
        $expected = $this->connection->fetchFirstColumn('SELECT id FROM projects ORDER BY created_at, id');
        $before = $this->counts();
        $ids = [];
        foreach ([0, 2, 4] as $offset) {
            $page = $this->assertResponse($this->request('GET', '?limit=2&offset='.$offset), 200, 'list')['data'];
            array_push($ids, ...array_column($page['items'], 'project_id'));
            self::assertSame($offset < 4, $page['has_more']);
        }
        self::assertSame($expected, $ids);
        self::assertSame($before, $this->counts());
        $this->assertResponse($this->request('GET', '?limit=101'), 400, 'list');
        $this->assertResponse($this->request('GET', '/'.ProjectId::generate()->toString()), 404, 'get');
    }

    /**
     * Refuses untrusted identity/replay fields and unsafe source input without echo or mutation
     *
     * @param array<string, mixed> $extra
     */
    #[DataProvider('invalidInputs')]
    public function testRejectedInput(array $extra, int $status): void
    {
        $before = $this->counts();
        $response = $this->request('POST', '', array_replace($this->input('invalid'), $extra));
        $this->assertResponse($response, $status, 'register');
        self::assertSame($before, $this->counts());
        self::assertSame([], $this->events);
        self::assertStringNotContainsString(
            'secret-marker',
            (string) $response->getBody().json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Supplies closed transport and Domain refusal cases
     *
     * @return iterable<string, array{array<string, mixed>, int}>
     */
    public static function invalidInputs(): iterable
    {
        foreach (
            [
            'project_id', 'workspace_id', 'actor_id', 'revision', 'created_at', 'lifecycle', 'directory',
            'idempotency_key'
            ] as $field
        ) {
            yield $field => [[$field => 'secret-marker'], 400];
        }
        yield 'reserved slug' => [['slug' => 'api'], 422];
        yield 'coerced slug' => [['slug' => 'Project'], 422];
        yield 'invalid name' => [['display_name' => ' bad '], 422];
        yield 'credential locator' => [['primary_source' => [
            'type' => 'git_network_v1', 'locator' => 'https://user:secret-marker@example.test/repo'
        ]], 422];
        yield 'local authority' => [['primary_source' => [
            'type' => 'git_local_v1', 'locator' => '/private/secret-marker'
        ]], 422];
        yield 'provider authority' => [
            ['primary_source' => [
                'type'        => 'github_repository_v1',
                'locator'     => 'https://github.com/team/repo',
                'provider_id' => 'secret-marker'
            ]],
            422
        ];
        yield 'scalar primary' => [['primary_source' => 'secret-marker'], 400];
        yield 'equivalents object' => [['equivalent_sources' => (object) ['private' => 'secret-marker']], 400];
        yield 'equivalents scalar' => [['equivalent_sources' => ['secret-marker']], 422];
        yield 'unanchored equivalent' => [['equivalent_sources' => [[
            'type' => 'git_network_v1', 'locator' => 'https://example.test/a'
        ]]], 422];
    }

    /**
     * Reports invalid persisted facts as sanitized internal faults on otherwise valid reads
     *
     * @param 'source'|'metadata'|'sources' $fault
     */
    #[DataProvider('persistedFaults')]
    public function testPersistedFaultIsNotSubmittedInput(string $fault, string $operation): void
    {
        $input = $this->input('stored-fault');
        $input['primary_source'] = ['type' => 'git_network_v1', 'locator' => 'https://github.com/team/secret-marker'];
        $id = $this->assertResponse($this->request('POST', '', $input), 201, 'register')['data']['project_id'];
        match ($fault) {
            'source' => $this->connection->update('project_sources', [
                'source_key' => json_encode([
                    'type'       => 'github_repository_v1',
                    'authority'  => 'github.com',
                    'owner'      => 'TEAM',
                    'repository' => 'secret-marker'
                ], JSON_THROW_ON_ERROR)
            ], ['project_id' => $id]),
            'metadata' => $this->connection->update('projects', [
                'display_name' => ' secret-marker '
            ], ['id' => $id]),
            'sources' => $this->connection->update('project_sources', [
                'source_role' => 'equivalent'
            ], ['project_id' => $id])
        };
        $before = $this->counts();
        $events = $this->events;
        $response = $this->request('GET', $operation === 'get' ? '/'.$id : '');
        $body = $this->assertResponse($response, 500, $operation);
        self::assertSame('error', $body['status']);
        self::assertSame($before, $this->counts());
        self::assertSame($events, $this->events);
        foreach (['secret-marker', 'TEAM', $id, 'ProjectInputRejected'] as $private) {
            self::assertStringNotContainsString($private, (string) $response->getBody());
        }
        self::assertStringNotContainsString(
            'secret-marker',
            json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Supplies independently admitted reads over malformed stored source, metadata and equivalence facts
     *
     * @return iterable<string, array{string, string}>
     */
    public static function persistedFaults(): iterable
    {
        foreach (['source', 'metadata', 'sources'] as $fault) {
            foreach (['get', 'list'] as $operation) {
                yield $fault.' '.$operation => [$fault, $operation];
            }
        }
    }

    /**
     * Admits read and register independently and checks fresh permission after revocation
     */
    public function testIndependentAdmission(): void
    {
        $id = $this->assertResponse(
            $this->request('POST', '', $this->input('alpha')),
            201,
            'register'
        )['data']['project_id'];
        $this->revokeOperation('READ_PROJECTS');
        $this->assertResponse($this->request('GET'), 403, 'list');
        $this->assertResponse($this->request('GET', '/'.$id), 403, 'get');
        $this->assertResponse($this->request('POST', '/conflict-preview', $this->input('alpha')), 403, 'preview');
        $this->assertResponse($this->request('POST', '', $this->input('beta')), 201, 'register');
        $this->assertResponse($this->request('POST', '', $this->input('alpha')), 409, 'register');
        $this->revokeOperation('REGISTER_PROJECTS');
        $this->assertResponse($this->request('POST', '', $this->input('gamma')), 403, 'register');
    }

    /**
     * Refuses absent authentication, revoked sessions and unknown Workspace scope before effects
     */
    public function testAuthenticationAndWorkspaceRefusals(): void
    {
        $before = $this->counts();
        $this->assertResponse($this->request('POST', '', $this->input('anonymous'), null, false), 401, 'register');
        $this->assertResponse($this->request('GET', '', null, WorkspaceId::generate()), 403, 'list');
        $this->assertResponse(
            $this->request('POST', '', $this->input('wrong-scope'), WorkspaceId::generate()),
            403,
            'register'
        );
        $sessions = $this->container->get(RefreshSessionRepository::class);
        self::assertTrue($this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn(): bool => $sessions->replace($this->session, $this->session->revoke())
        ));
        $this->assertResponse($this->request('GET'), 401, 'list');
        self::assertSame($before, $this->counts());
    }

    /**
     * Keeps Workspace access independent of operation permission and never implicitly bootstraps
     */
    public function testWorkspaceMembershipAndBootstrapAbsence(): void
    {
        $users = $this->container->get(UserRepository::class);
        $expected = $users->getById($this->actor->getId());
        self::assertInstanceOf(User::class, $expected);
        $replacement = clone $expected;
        $replacement->removeRole(RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID), $this->time);
        self::assertTrue($this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn(): bool => $users->replaceRoleAssignments($expected, $replacement)
        ));
        $this->assertResponse($this->request('POST', '', $this->input('denied')), 403, 'register');
        $this->assertResponse($this->request('GET'), 403, 'list');
        $this->connection->executeStatement('TRUNCATE workspaces CASCADE');
        $this->assertResponse($this->request('GET'), 403, 'list');
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM workspaces'));
    }

    /**
     * Translates only named final constraints and rolls back a losing partial source set
     */
    #[DataProvider('constraintKinds')]
    public function testFinalConstraintTranslation(string $kind): void
    {
        $input = $this->input('owned');
        $input['primary_source'] = ['type' => 'git_network_v1', 'locator' => 'https://example.test/owned'];
        $id = $this->assertResponse($this->request('POST', '', $input), 201, 'register')['data']['project_id'];
        $before = $this->counts();
        $project = Project::register(
            $kind === 'unknown' ? ProjectId::fromString($id) : ProjectId::generate(),
            $this->workspace,
            new ProjectMetadata('Contender', $kind === 'slug' ? 'owned' : 'contender'),
            new ProjectSources($kind === 'source' ? SourceDescriptor::fromInput($input['primary_source']) : null),
            $this->actor->getId(),
            $this->time
        );
        try {
            $this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
                fn() => $this->container->get(ProjectRepository::class)->add($project)
            );
            self::fail('Final constraint accepted');
        } catch (ProjectConflict $failure) {
            self::assertSame($kind, $failure->reason);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            self::assertSame('unknown', $kind);
        }
        self::assertSame($before, $this->counts());
    }

    /**
     * Supplies recognized business conflicts and an unclassified identity collision
     *
     * @return iterable<array{string}>
     */
    public static function constraintKinds(): iterable
    {
        yield ['slug'];
        yield ['source'];
        yield ['unknown'];
    }

    /**
     * Chooses one winner under real simultaneous HTTP slug/source claims
     */
    #[DataProvider('raceKinds')]
    public function testCompetingHttpClaims(string $kind): void
    {
        $first = $this->input('race-one');
        $second = $this->input($kind === 'slug' ? 'race-one' : 'race-two');
        if ($kind === 'source') {
            $first['primary_source'] = ['type' => 'git_network_v1', 'locator' => 'https://github.com/team/race.git'];
            $second['primary_source'] = ['type' => 'github_repository_v1', 'locator' => 'git@github.com:TEAM/RACE'];
        }
        $results = $this->compete([$this->workerInput($first), $this->workerInput($second)]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        self::assertSame([201, 409], $statuses);
        self::assertSame([1, 1, $kind === 'source' ? 1 : 0, 2, 0], $this->counts());
        $winner = $results[0]['status'] === 201 ? $results[0] : $results[1];
        $loser = $results[0]['status'] === 409 ? $results[0] : $results[1];
        self::assertStringNotContainsString(
            $winner['body']['data']['project_id'],
            json_encode($loser['body'], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Supplies authoritative concurrent claim types
     *
     * @return iterable<array{string}>
     */
    public static function raceKinds(): iterable
    {
        yield ['slug'];
        yield ['source'];
    }

    /**
     * Allows an admitted request to finish after completed revocation and refuses the next request
     */
    public function testAdmissionTimeRevocationPolicy(): void
    {
        $worker = $this->startWorker([...$this->workerInput($this->input('admitted')), 'barrier' => 'admitted']);
        try {
            self::assertSame('admitted', trim((string) fgets($worker['pipes'][1])));
            // The revocation commits while the request is paused before its command transaction.
            $this->revokeOperation('REGISTER_PROJECTS');
            $this->releaseWorker($worker);
            self::assertSame(201, $this->finishWorker($worker)['status']);
            $this->assertResponse($this->request('POST', '', $this->input('next')), 403, 'register');
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM projects'));
        } finally {
            $this->cleanWorker($worker);
        }
    }

    /**
     * Preserves explicit bootstrap current-state inspection and atomic audit
     */
    public function testExplicitBootstrapCurrentStateAndAuditRollback(): void
    {
        $console = new CommandTester($this->container->get(BootstrapWorkspaceCommand::class));
        $intent = [
            '--internal' => true, '--confirm-bootstrap' => true, '--operator' => $this->actor->getId()->toString()
        ];
        self::assertSame(0, $console->execute($intent));
        self::assertSame($this->workspace->toString(), json_decode($console->getDisplay(), true)['workspace_id']);
        self::assertSame([1, 0, 0, 1, 0], $this->counts());
        $this->connection->executeStatement('TRUNCATE workspaces CASCADE');
        $this->connection->executeStatement(<<<'SQL'
ALTER TABLE workspace_audit ADD CONSTRAINT project_fixture_abort CHECK (action <> 'workspace_bootstrapped') NOT VALID
SQL);
        $this->freshApplication();
        self::assertNull($this->container->get(QueryBus::class)->fetch(new FindInitialWorkspace()));
        $console = new CommandTester($this->container->get(BootstrapWorkspaceCommand::class));
        self::assertSame(1, $console->execute($intent));
        self::assertSame([0, 0, 0, 0, 0], $this->counts());
    }

    /**
     * Keeps reference generation non-reserving with independent bounded atomic admission
     */
    public function testConcurrentReferencesAndRollover(): void
    {
        $before = $this->counts();
        $inputs = array_fill(0, 12, [...$this->workerInput([]), 'mode' => 'reference']);
        $results = $this->compete($inputs);
        $statuses = array_column($results, 'status');
        sort($statuses);
        self::assertSame([...array_fill(0, 10, 200), 429, 429], $statuses);
        self::assertSame($before, $this->counts());
        foreach ($results as $result) {
            if ($result['status'] === 200) {
                self::assertFalse($result['body']['data']['reserved']);
                self::assertSame(
                    $result['body']['data']['reference'],
                    ProjectId::fromString($result['body']['data']['reference'])->toString()
                );
            } else {
                self::assertSame('60', $result['retry_after']);
            }
        }
        $this->time = $this->time->modify('+59 seconds');
        $this->assertResponse($this->request('POST', '/references'), 429, 'reference');
        $this->time = $this->time->modify('+1 second');
        $this->assertResponse($this->request('POST', '/references'), 200, 'reference');
        $this->assertResponse($this->request('POST', '/references', ['actor' => 'secret-marker']), 400, 'reference');
        $this->revokeOperation('GENERATE_REFERENCE_IDS');
        $this->assertResponse($this->request('POST', '/references'), 403, 'reference');
    }

    /**
     * Bounds reference bucket capacity while retaining an existing actor's remaining budget
     */
    public function testReferenceBucketCapacity(): void
    {
        $window = $this->time->getTimestamp();
        for ($batch = 0; $batch < 10; ++$batch) {
            $ids = [];
            for ($index = 0; $index < 1000; ++$index) {
                $ids[] = UserId::generate()->toString();
            }
            $sql = 'INSERT INTO reference_generation_limits (actor_id, window_start, attempts) VALUES ';
            $values = implode(',', array_fill(0, 1000, '(?, '.$window.', 1)'));
            $this->connection->executeStatement($sql.$values, $ids);
        }
        $this->assertResponse($this->request('POST', '/references'), 429, 'reference');
        self::assertSame(10000, (int) $this->connection->fetchOne('SELECT count(*) FROM reference_generation_limits'));
        $this->connection->delete('reference_generation_limits', ['actor_id' => $ids[0]]);
        $this->assertResponse($this->request('POST', '/references'), 200, 'reference');
        $this->assertResponse($this->request('POST', '/references'), 200, 'reference');
        self::assertSame(10000, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM reference_generation_limits WHERE window_start = ?',
            [$window]
        ));
    }

    /**
     * Preserves one initial Workspace winner under competing trusted commands and Console entries
     */
    public function testConcurrentBootstrapEntries(): void
    {
        foreach (['bootstrap', 'bootstrap_console'] as $mode) {
            $this->connection->executeStatement('TRUNCATE workspaces CASCADE');
            $results = $this->compete(array_fill(0, 2, [...$this->workerInput([]), 'mode' => $mode]));
            $statuses = array_column($results, 'status');
            sort($statuses);
            self::assertSame(['bootstrapped', 'conflict'], $statuses);
            self::assertSame([1, 0, 0, 1, 0], $this->counts());
        }
    }

    /**
     * Refuses repeated nested keys before source interpretation
     */
    public function testNestedDuplicateKeys(): void
    {
        $this->freshApplication();
        $before = $this->counts();
        $request = (new ServerRequestFactory())->createServerRequest(
            'POST',
            'https://localhost/api/v1/workspaces/'.$this->workspace->toString().'/projects'
        )
            ->withHeader('Authorization', 'Bearer '.$this->token)->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(<<<'JSON'
{"display_name":"Project","slug":"project","primary_source":{
    "type":"git_network_v1","locator":"https://example.test/a","locator":"https://example.test/secret-marker"
}}
JSON);
        $this->assertResponse($this->app->handle($request), 400, 'register');
        self::assertSame($before, $this->counts());
    }

    /**
     * Supplies ordinary creation input without replay metadata
     *
     * @return array<string, mixed>
     */
    private function input(string $slug): array
    {
        return ['display_name' => 'Project '.$slug, 'slug' => $slug];
    }

    /**
     * Executes one actual independently authenticated HTTP request
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
        $path .= $suffix === '/references' ? $suffix : '/projects'.$suffix;
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
     * Checks actual wire responses and non-disclosure against the generated operation contract
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
            'register' => ['/projects', 'post'], 'get' => ['/projects/{project_id}', 'get'],
            'list' => ['/projects', 'get'], 'preview' => ['/projects/conflict-preview', 'post'],
            'reference' => ['/references', 'post'], default => throw new \LogicException('Unknown operation')
        };
        (new ValidatorBuilder())->fromJson($json)->getResponseValidator()->validate(
            new OperationAddress('/api/v1/workspaces/{workspace_id}'.$path, $method),
            $response
        );
        foreach (['secret-marker', 'SQLSTATE', 'uq_workspace', 'password_hash', 'credential_digest'] as $secret) {
            self::assertStringNotContainsString($secret, (string) $response->getBody());
        }

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Counts authoritative effects separately from technical rate metadata
     *
     * @return list<int>
     */
    private function counts(): array
    {
        return array_map(
            fn(string $table): int => (int) $this->connection->fetchOne('SELECT count(*) FROM '.$table),
            ['workspaces', 'projects', 'project_sources', 'workspace_audit', 'workspace_history']
        );
    }

    /**
     * Boots fresh request state after successful or failed handler transactions
     */
    private function freshApplication(): void
    {
        $this->app = WorkspaceTestClock::application($this->time);
        $this->container = $this->app->getContainer();
        $this->container->get('http.failure.logger')->setHandlers([$this->logs]);
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(ProjectRegistered::class),
            function (): void {
                $this->events[] = [
                    $this->container->get(Connection::class)->isTransactionActive(),
                    (int) $this->connection->fetchOne(
                        "SELECT count(*) FROM workspace_audit WHERE action = 'project_registered'"
                    )
                ];
                if ($this->dispatchThrows) {
                    throw new RuntimeException('secret-marker');
                }
            }
        );
    }

    /**
     * Commits current permission revocation through the real owning writer
     */
    private function revokeOperation(string $name): void
    {
        $this->freshApplication();
        $roles = $this->container->get(RoleRepository::class);
        $expected = $roles->getById($this->operationRole->getId());
        $permission = $this->container->get(PermissionRepository::class)->getByName(PermissionName::fromString($name));
        $replacement = $expected->revokePermissionFromCustom($permission->getId(), $this->time);
        self::assertTrue($this->container->get(TransactionalUnitOfWork::class)->commitTransactional(
            fn(): bool => $roles->replace($expected, $replacement)
        ));
    }

    /**
     * Supplies safe invocation fields to independent workers
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function workerInput(array $body): array
    {
        return [
            'mode'      => 'register',
            'clock'     => $this->time->format(DATE_ATOM),
            'body'      => $body,
            'workspace' => $this->workspace->toString(),
            'actor'     => $this->actor->getId()->toString(),
            'token'     => $this->token
        ];
    }

    /**
     * Releases simultaneous guarded invocations and collects bounded results
     *
     * @param list<array<string, mixed>> $inputs
     *
     * @return list<array<string, mixed>>
     */
    private function compete(array $inputs): array
    {
        $workers = [];
        try {
            foreach ($inputs as $input) {
                $workers[] = $this->startWorker($input);
            }
            foreach ($workers as $worker) {
                self::assertSame('ready', trim((string) fgets($worker['pipes'][1])));
            }
            foreach ($workers as $worker) {
                $this->releaseWorker($worker);
            }
            $results = [];
            foreach ($workers as &$worker) {
                $results[] = $this->finishWorker($worker);
            }
            unset($worker);

            return $results;
        } finally {
            foreach ($workers as $worker) {
                $this->cleanWorker($worker);
            }
        }
    }

    /**
     * Starts one isolated connection and protocol barrier
     *
     * @param array<string, mixed> $input
     *
     * @return array{process: resource, pipes: array<int, resource>, finished: bool}
     */
    private function startWorker(array $input): array
    {
        $pipes = [];
        $process = proc_open([
            PHP_BINARY, '-r', 'require "vendor/autoload.php"; Tests\\Support\\ProjectRaceWorker::run();'
        ], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
        self::assertIsResource($process);
        stream_set_timeout($pipes[1], 20);
        fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR)."\n");
        fflush($pipes[0]);
        self::assertGreaterThan(0, (int) fgets($pipes[1]));

        return ['process' => $process, 'pipes' => $pipes, 'finished' => false];
    }

    /**
     * Releases one observed barrier
     *
     * @param array{process: resource, pipes: array<int, resource>, finished: bool} $worker
     */
    private function releaseWorker(array $worker): void
    {
        fwrite($worker['pipes'][0], "go\n");
        fflush($worker['pipes'][0]);
    }

    /**
     * Collects one safe result and successful worker exit
     *
     * @param array{process: resource, pipes: array<int, resource>, finished: bool} $worker
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
     * Stops only an unfinished fixture worker after an interrupted assertion
     *
     * @param array{process: resource, pipes: array<int, resource>, finished: bool} $worker
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
}
