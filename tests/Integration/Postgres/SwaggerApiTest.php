<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Documentation\PrivateOpenApi;
use App\Adapter\Http\Web\PublicRuntimeConfiguration;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\RefreshSessionRecords;
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
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Value\Internet\EmailAddress;
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
use Tests\Support\AccessClock;
use Tests\Support\AccessTokens;

/**
 * Proves both real documentation routes against current PostgreSQL authority and controlled policy composition
 */
final class SwaggerApiTest extends TestCase
{
    private Connection $connection;
    private PostgresUserRepository $users;
    private PostgresRoleRepository $roles;
    private PostgresRefreshSessionRepository $sessions;
    private User $user;
    private RefreshSession $session;
    private AccessTokens $tokens;
    private string $token;
    private string $directory;
    private string $document;
    /** @var array<string, string|false> */
    private array $environment;
    /** @var App<Container> */
    private App $app;
    private Container $container;

    /**
     * Creates disposable package authority on the guarded database before selecting simulated environment policy
     */
    protected function setUp(): void
    {
        $this->environment = [
            'DATABASE_URL'            => getenv('DATABASE_URL'),
            'APP_ACCESS_TOKEN_CONFIG' => getenv('APP_ACCESS_TOKEN_CONFIG')
        ];
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse($url));
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
            new AuthenticationAuthorityFences($this->connection)
        );
        $this->tokens = new AccessTokens();
        $this->tokens->clock->time = new DateTimeImmutable('2026-09-28T12:00:00Z');
        $now = $this->tokens->clock->now()->modify('-1 minute');
        $this->user = User::invite(UserId::generate(), EmailAddress::fromString('swagger@example.test'), $now);
        $this->user->activate(
            PasswordHash::fromString((string) password_hash('private-fixture', PASSWORD_BCRYPT)),
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
        putenv('DATABASE_URL='.$url);
        putenv('APP_ACCESS_TOKEN_CONFIG='.json_encode($this->tokens->configuration, JSON_THROW_ON_ERROR));
        $this->container = require dirname(__DIR__, 3).'/config/services.php';
        $this->container->set(SystemAccessClock::class, fn(): AccessClock => $this->tokens->clock);
        $this->container->get(SynchronousCommandBus::class)->execute(
            new ReconcileManagedPolicy(InstallationManagedPolicy::create())
        );
        $this->directory = dirname(__DIR__, 3).'/var/tests/swagger-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        // Runtime-reader input, not a test of the export tool or generated-file freshness.
        $this->document = json_encode([
            'openapi'         => '3.0.3',
            'info'            => ['title' => 'Private contract', 'version' => '1'],
            'paths'           => (object) ['/api/v1/me' => (object) []],
            'x-source-sha256' => str_repeat('a', 64)
        ], JSON_THROW_ON_ERROR);
        file_put_contents($this->directory.'/document.json', $this->document);
        $this->container->set(PrivateOpenApi::class, fn(): PrivateOpenApi =>
            new PrivateOpenApi($this->directory.'/document.json'));
    }

    /**
     * Restores private settings and removes only owned runtime-reader inputs
     */
    protected function tearDown(): void
    {
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        if (is_file($this->directory.'/document.json')) {
            unlink($this->directory.'/document.json');
        }
        if (is_link($this->directory.'/link.json')) {
            unlink($this->directory.'/link.json');
        }
        rmdir($this->directory);
        $this->connection->close();
    }

    /**
     * Requires exact eligibility independently on bootstrap and direct JSON even for debug-enabled environments
     */
    #[DataProvider('environments')]
    public function testEnvironmentPolicy(
        string|false $name,
        string|false $allowed,
        string $debug,
        bool $eligible
    ): void {
        $this->grantSuperAdmin();
        $this->boot(PublicRuntimeConfiguration::parse($name, $debug, $allowed));
        foreach (['bootstrap', 'openapi'] as $resource) {
            $response = $this->request($resource, $this->token);
            $this->assertSafe($response, $eligible ? 200 : 404);
            if (!$eligible) {
                self::assertStringNotContainsString('Private contract', (string) $response->getBody());
                self::assertStringNotContainsString('viewer_version', (string) $response->getBody());
            } elseif ($resource === 'openapi') {
                self::assertSame($this->document, (string) $response->getBody());
            } else {
                self::assertSame([
                    'status' => 'success',
                    'data'   => [
                        'viewer_version' => 1,
                        'document_path'  => '/api/v1/documentation/openapi'
                    ]
                ], json_decode((string) $response->getBody(), true));
            }
        }
    }

    /**
     * Supplies explicit positive policy contexts without pretending to perform production HTTP startup
     *
     * @return iterable<string, array{string|false, string|false, string, bool}>
     */
    public static function environments(): iterable
    {
        foreach (['local', 'test', 'staging', 'prod', 'production'] as $name) {
            yield 'opted in '.$name => [$name, json_encode([$name], JSON_THROW_ON_ERROR), 'false', true];
            yield 'empty '.$name => [$name, '[]', 'false', false];
        }
        yield 'debug independent' => ['staging', '["staging"]', 'true', true];
        yield 'debug cannot enable' => ['staging', '["local"]', 'true', false];
        foreach ([false, '', 'broken', '{}', '["*"]', '["local",false]', '["local","local"]'] as $index => $list) {
            yield 'invalid list '.$index => ['local', $list, 'false', false];
        }
        yield 'unset environment' => [false, '["local"]', 'false', false];
        yield 'invalid environment' => ['LOCAL', '["local"]', 'false', false];
        yield 'legacy environment' => ['development', '["local"]', 'false', false];
    }

    /**
     * Rechecks anonymous, insufficient, revoked, stale and unavailable authority before either disclosure
     */
    #[DataProvider('authorityCases')]
    public function testCurrentAuthority(string $case, int $status): void
    {
        $logs = new TestHandler(Level::Error);
        $logs->setFormatter(new JsonFormatter());
        // Only the deliberate unavailable-repository case replaces stderr; unexpected failures stay visible.
        if ($case === 'unavailable') {
            $this->container->get('http.failure.logger')->setHandlers([$logs]);
        }
        if ($case !== 'insufficient') {
            $this->grantSuperAdmin();
        }
        if ($case === 'revoked permission') {
            $role = $this->roles->getByName(RoleName::fromString('ROLE_SUPER_ADMIN'));
            self::assertNotNull($role);
            $replacement = clone $this->user;
            $replacement->removeRole($role->getId(), $this->tokens->clock->now());
            $this->connection->transactional(fn() => $this->users->replaceRoleAssignments($this->user, $replacement));
        } elseif ($case === 'revoked session') {
            $this->connection->transactional(
                fn() => $this->sessions->replace($this->session, $this->session->revoke())
            );
        } elseif ($case === 'expired') {
            $this->tokens->clock->time = $this->tokens->clock->now()->modify('+16 minutes');
        } elseif ($case === 'unavailable') {
            $repository = $this->createStub(PermissionRepository::class);
            $repository->method('getByName')->willThrowException(new \RuntimeException('private-repository-detail'));
            $this->container->set(PermissionRepository::class, static fn(): PermissionRepository => $repository);
        }
        $this->boot(new PublicRuntimeConfiguration(true, true));
        foreach (['bootstrap', 'openapi'] as $resource) {
            $response = $this->request($resource, $case === 'anonymous' ? null : $this->token);
            $this->assertSafe($response, $status);
            self::assertStringNotContainsString('viewer_version', (string) $response->getBody());
            self::assertStringNotContainsString('Private contract', (string) $response->getBody());
            self::assertStringNotContainsString('private-repository-detail', (string) $response->getBody());
            if ($case === 'unavailable') {
                self::assertCount(1, $logs->getRecords());
                $record = $logs->getRecords()[0];
                self::assertSame(Level::Error, $record->level);
                self::assertSame('http-failure', $record->channel);
                self::assertSame('Unhandled HTTP request failure.', $record->message);
                self::assertMatchesRegularExpression(
                    '/\A[a-f0-9]{32}\z/',
                    $response->getHeaderLine('X-Correlation-ID')
                );
                self::assertSame([
                    'correlation_id'     => $response->getHeaderLine('X-Correlation-ID'),
                    'correlation_source' => 'generated',
                    'request_method'     => 'GET',
                    'exception_type'     => \RuntimeException::class
                ], $record->context);
                self::assertSame([], $record->extra);
                self::assertIsString($record->formatted);
                $secrets = ['private-repository-detail', $this->token, $this->session->getId()->toString()];
                foreach ($secrets as $secret) {
                    self::assertStringNotContainsString($secret, $record->formatted);
                }
                $logs->clear();
            }
        }
    }

    /**
     * Rejects authority removed between bootstrap and the direct document read on the same application
     */
    public function testRevocationBetweenRequests(): void
    {
        $this->grantSuperAdmin();
        $this->boot(new PublicRuntimeConfiguration(false, true));
        $this->assertSafe($this->request('bootstrap', $this->token), 200);
        $role = $this->roles->getByName(RoleName::fromString('ROLE_SUPER_ADMIN'));
        self::assertNotNull($role);
        $replacement = clone $this->user;
        $replacement->removeRole($role->getId(), $this->tokens->clock->now());
        $this->connection->transactional(fn() => $this->users->replaceRoleAssignments($this->user, $replacement));
        $this->assertSafe($this->request('openapi', $this->token), 403);
        $this->assertSafe($this->request('bootstrap', $this->token), 403);
    }

    /**
     * Supplies independent authority outcomes
     *
     * @return iterable<string, array{string, int}>
     */
    public static function authorityCases(): iterable
    {
        foreach (
            [
            'anonymous'          => 401,
            'insufficient'       => 403,
            'revoked permission' => 403,
            'revoked session'    => 401,
            'expired'            => 401,
            'unavailable'        => 500
            ] as $case => $status
        ) {
            yield $case => [$case, $status];
        }
    }

    /**
     * Refuses missing, malformed and externally referencing runtime documents without a public alias
     */
    #[DataProvider('artifacts')]
    public function testUnavailableArtifact(string $case): void
    {
        $this->grantSuperAdmin();
        if ($case === 'missing') {
            unlink($this->directory.'/document.json');
        } else {
            $value = json_decode($this->document, true, 16, JSON_THROW_ON_ERROR);
            if ($case === 'remote ref') {
                $value['components'] = ['$ref' => 'https://example.invalid/private'];
            } elseif ($case === 'server override') {
                $value['paths']['/api/v1/me']['servers'] = [['url' => 'https://example.invalid']];
            } elseif ($case === 'version') {
                $value['openapi'] = '3.1.0';
            }
            file_put_contents(
                $this->directory.'/document.json',
                $case === 'corrupt' ? '{' : json_encode($value, JSON_THROW_ON_ERROR)
            );
        }
        $this->boot(new PublicRuntimeConfiguration(false, true));
        $this->assertSafe($this->request('openapi', $this->token), 503);
        $this->assertSafe($this->request('bootstrap', $this->token), 200);
        foreach (
            [
            '/openapi.json', '/swagger.json', '/build/openapi.json', '/var/openapi/openapi.json',
            '/api/v1/documentation/openapi/components/schemas'
            ] as $path
        ) {
            self::assertSame(404, $this->request('', $this->token, $path)->getStatusCode());
        }
    }

    /**
     * Supplies runtime document refusal states
     *
     * @return iterable<string, array{string}>
     */
    public static function artifacts(): iterable
    {
        foreach (['missing', 'corrupt', 'remote ref', 'server override', 'version'] as $case) {
            yield $case => [$case];
        }
    }

    /**
     * Installs the ordinary managed membership without inventing a READ_SWAGGER direct grant
     */
    private function grantSuperAdmin(): void
    {
        $role = $this->roles->getByName(RoleName::fromString('ROLE_SUPER_ADMIN'));
        self::assertNotNull($role);
        $replacement = clone $this->user;
        $replacement->assignRole($role->getId(), $this->tokens->clock->now());
        self::assertTrue($this->connection->transactional(
            fn() => $this->users->replaceRoleAssignments($this->user, $replacement)
        ));
        $this->user = $replacement;
    }

    /**
     * Composes real routing/security with a controlled environment policy and unchanged guarded test mode
     */
    private function boot(PublicRuntimeConfiguration $configuration): void
    {
        self::assertSame('test', getenv('APP_ENV'));
        $this->container->set(
            PublicRuntimeConfiguration::class,
            static fn(): PublicRuntimeConfiguration => $configuration
        );
        AppFactory::setContainer($this->container);
        AppFactory::setResponseFactory(new ResponseFactory());
        /** @var App<Container> $app */
        $app = AppFactory::create();
        $this->app = $app;
        $container = $this->container;
        require dirname(__DIR__, 3).'/config/routes.php';
        require dirname(__DIR__, 3).'/config/middleware.php';
    }

    /**
     * Invokes the actual matched Action with a fresh request-local principal
     */
    private function request(string $resource, ?string $token, ?string $path = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'https://localhost:18443'.($path ?? '/api/v1/documentation/'.$resource)
        );
        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer '.$token);
        }

        return $this->app->handle($request);
    }

    /**
     * Checks disclosure and cache/security headers without retaining credentials in evidence
     */
    private function assertSafe(ResponseInterface $response, int $status): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        self::assertStringNotContainsString($this->token, (string) $response->getBody());
        self::assertStringNotContainsString($this->session->getId()->toString(), (string) $response->getBody());
    }
}
