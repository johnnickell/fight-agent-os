<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Api\Auth\RequestAuthenticationContext;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Adapter\Security\AccessToken\AccessJwt;
use App\Application\Failure\PermissionDenied;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use App\Application\Sessions\BrowserFamily;
use App\Application\Sessions\DeviceClass;
use App\Application\Sessions\DeviceDescriptor;
use App\Application\Sessions\ReadOwnedSessions;
use App\Application\Sessions\SessionPresentation;
use App\Domain\Authorization\InstallationManagedPolicy;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\ListActiveSessions;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Repository\Pagination;
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
 * Exercises principal-only session visibility through real HTTP, package dispatch and PostgreSQL
 */
final class OwnedSessionsApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private User $actor;
    private RefreshSession $session;
    private AccessTokens $tokens;
    private string $token;
    private CatalogQueryProbe $probe;
    /** @var array<string, string|false> */
    private array $environment = [];

    /**
     * Boots actual routes against only the guarded task test database
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
        $this->tokens = new AccessTokens();
        $this->tokens->clock->time = new DateTimeImmutable('2026-10-25T12:00:00Z');
        putenv('DATABASE_URL='.$url);
        putenv('APP_ACCESS_TOKEN_CONFIG='.json_encode($this->tokens->configuration, JSON_THROW_ON_ERROR));
        $container = require dirname(__DIR__, 3).'/config/services.php';
        self::assertInstanceOf(Container::class, $container);
        $this->container = $container;
        $container->set(Clock::class, fn(): Clock => $this->tokens->clock);
        $container->set(AccessJwt::class, fn(): AccessJwt => $this->tokens->jwt);
        $container->set(AuthenticateAccessToken::class, fn(Container $c) => new AuthenticateAccessToken(
            $this->tokens->jwt,
            $c->get(UserRepository::class),
            $c->get(RefreshSessionRepository::class),
            $this->tokens->clock
        ));
        $container->set(AuthoritativePrincipalResolver::class, fn(Container $c) => new AuthoritativePrincipalResolver(
            $c->get(UserRepository::class),
            $c->get(RefreshSessionRepository::class),
            $c->get(RoleRepository::class),
            $c->get(PermissionRepository::class),
            $this->tokens->clock
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
        $this->actor = $this->user('owner@example.test');
        $this->session = $this->addSession($this->actor, $this->tokens->clock->now()->modify('-1 hour'));
        $claims = $this->tokens->claims();
        $claims['sub'] = $this->actor->getId()->toString();
        $claims['sid'] = $this->session->getId()->toString();
        $claims['auth_version'] = $this->actor->getAuthenticationVersion();
        $this->token = $this->tokens->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        $this->probe = new CatalogQueryProbe();
        $container->get('messaging.query.bus')->addFilter($this->probe);
    }

    /**
     * Restores caller environment and removes only this test's fault injection
     */
    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE session_presentation DROP CONSTRAINT IF EXISTS presentation_abort'
        );
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        $this->connection->close();
    }

    /**
     * Proves exact permission rather than Role membership or an administrative read permission
     */
    public function testExactPermissionAndNonHttpBoundary(): void
    {
        self::assertSame(401, $this->request('', false)->getStatusCode());
        $this->connection->executeStatement(<<<'SQL'
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = 'READ_OWN_SESSIONS')
SQL);
        self::assertSame(403, $this->request()->getStatusCode());
        self::assertSame(0, $this->probe->count());
        $this->expectException(PermissionDenied::class);
        $this->container->get(ReadOwnedSessions::class)->read($this->provider());
    }

    /**
     * Proves disjoint tied pages, package filtering and explicit redacted Views
     */
    public function testOwnedFilteringAndPagination(): void
    {
        $other = $this->user('other@example.test');
        $this->addSession($other, $this->session->getCreatedAt());
        $remembered = $this->addSession($this->actor, $this->session->getCreatedAt(), true);
        $expired = $this->addSession($this->actor, $this->session->getCreatedAt());
        $revoked = $this->addSession($this->actor, $this->session->getCreatedAt());
        $absolute = $this->addSession($this->actor, $this->session->getCreatedAt());
        $at = RefreshSessionRecords::date($this->tokens->clock->now());
        $this->connection->executeStatement('UPDATE refresh_sessions SET idle_expires_at = ? WHERE id = ?', [
            $at, $expired->getId()->toString()
        ]);
        $this->connection->executeStatement('UPDATE refresh_sessions SET revoked = TRUE WHERE id = ?', [
            $revoked->getId()->toString()
        ]);
        $this->connection->executeStatement(
            'UPDATE refresh_sessions SET absolute_expires_at = ?, idle_expires_at = ? WHERE id = ?',
            [$at, $at, $absolute->getId()->toString()]
        );
        $presentation = $this->container->get(SessionPresentation::class);
        $presentation->capture(
            $remembered->getId(),
            new DeviceDescriptor(BrowserFamily::FIREFOX, DeviceClass::TABLET),
            $remembered->getAbsoluteExpiresAt()
        );
        $expected = [$this->session->getId()->toString(), $remembered->getId()->toString()];
        sort($expected);
        $ids = [];
        $current = 0;
        foreach ([1, 2] as $page) {
            $response = $this->request('?page='.$page.'&per_page=1');
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            (new ValidatorBuilder())->fromJson(Document::generate())->getResponseValidator()->validate(
                new OperationAddress('/api/v1/me/sessions', 'get'),
                $response
            );
            $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
            self::assertSame(2, $data['total_records']);
            self::assertSame(2, $data['total_pages']);
            $record = $data['records'][0];
            self::assertSame([
                'session_id', 'browser_family', 'device_class', 'created_at', 'last_activity_at',
                'idle_expires_at', 'absolute_expires_at', 'remembered', 'current'
            ], array_keys($record));
            $ids[] = $record['session_id'];
            $current += (int) $record['current'];
            if ($record['current']) {
                self::assertSame($this->session->getId()->toString(), $record['session_id']);
                self::assertSame('Unknown', $record['browser_family']);
            } else {
                self::assertTrue($record['remembered']);
                self::assertSame('Firefox', $record['browser_family']);
                self::assertSame('tablet', $record['device_class']);
            }
            self::assertStringNotContainsString($this->token, (string) $response->getBody());
            self::assertStringNotContainsString($this->session->getCredentialDigest(), (string) $response->getBody());
        }
        self::assertSame($expected, $ids);
        self::assertSame(1, $current);
        $empty = json_decode((string) $this->request('?page=1000&per_page=100')->getBody(), true)['data'];
        self::assertSame([], $empty['records']);
    }

    /**
     * Rejects ambiguous or forbidden selectors before any package query dispatch
     */
    #[DataProvider('invalidQueries')]
    public function testNoDispatchOnInvalidQuery(string $query): void
    {
        self::assertSame(400, $this->request('?'.$query)->getStatusCode());
        self::assertSame(0, $this->probe->count());
    }

    /**
     * Supplies duplicate, encoded, unknown and bounded pagination attacks
     *
     * @return iterable<array{string}>
     */
    public static function invalidQueries(): iterable
    {
        foreach (
            [
            'page=0', 'page=1001', 'per_page=0', 'per_page=101', 'page=-1', 'page=1.0',
            'page=1&page=2', 'page[]=1', 'page=1&user_id=x', 'actor_id=x', 'current_session_id=x',
            'sort=id', 'administrative=true', 'fields=credential_digest'
            ] as $query
        ) {
            yield [$query];
        }
    }

    /**
     * Ignores body identity claims and safely loses optional presentation on read failure
     */
    public function testBodyCannotSelectIdentityAndMissingMetadata(): void
    {
        $response = $this->request('', true, '{"user_id":"private-marker","current_session_id":"private-marker"}');
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(0, $this->probe->count());
        self::assertSame(200, $this->request('?%70age=%31&per_page=100')->getStatusCode());
        self::assertStringNotContainsString('private-marker', (string) $response->getBody());
        $this->container->get(Connection::class)->executeStatement(
            'ALTER TABLE session_presentation RENAME TO unavailable_presentation'
        );
        $connection = $this->container->get(Connection::class);
        $connection->beginTransaction();
        try {
            $response = $this->request();
            self::assertSame(200, $response->getStatusCode());
            $record = json_decode((string) $response->getBody(), true)['data']['records'][0];
            self::assertSame('Unknown', $record['browser_family']);
            self::assertSame(1, (int) $connection->fetchOne('SELECT 1'));
        } finally {
            $connection->rollBack();
            $connection->executeStatement('ALTER TABLE unavailable_presentation RENAME TO session_presentation');
        }
    }

    /**
     * Sanitizes authoritative read failures rather than treating missing authority as an empty result
     */
    public function testAuthoritativeReadFailure(): void
    {
        $this->connection->executeStatement('ALTER TABLE refresh_sessions RENAME TO unavailable_sessions');
        try {
            $response = $this->request();
            self::assertSame(401, $response->getStatusCode());
            self::assertStringNotContainsString('unavailable_sessions', (string) $response->getBody());
            self::assertStringNotContainsString('SQLSTATE', (string) $response->getBody());
            self::assertSame(0, $this->probe->count());
        } finally {
            $this->connection->executeStatement('ALTER TABLE unavailable_sessions RENAME TO refresh_sessions');
        }
    }

    /**
     * Denies stale authentication and independent package cross-owner dispatch
     */
    public function testStaleAndCrossOwner(): void
    {
        $this->connection->executeStatement('UPDATE refresh_sessions SET revoked = TRUE WHERE id = ?', [
            $this->session->getId()->toString()
        ]);
        self::assertSame(401, $this->request()->getStatusCode());
        self::assertSame(0, $this->probe->count());
        $this->expectException(SessionAdministrationAuthorizationException::class);
        $this->container->get(QueryBus::class)->fetch(new ListActiveSessions(
            $this->actor->getId(),
            UserId::generate(),
            $this->session->getId(),
            new Pagination(1, 25)
        ));
    }

    /**
     * Captures once, survives savepoint failures and prunes only presentation in bounded batches
     */
    public function testPresentationIdempotencyFailureAndRetention(): void
    {
        $store = $this->container->get(SessionPresentation::class);
        $store->capture(
            $this->session->getId(),
            new DeviceDescriptor(BrowserFamily::CHROME, DeviceClass::DESKTOP),
            $this->session->getAbsoluteExpiresAt()
        );
        $store->capture(
            $this->session->getId(),
            new DeviceDescriptor(BrowserFamily::SAFARI, DeviceClass::MOBILE),
            $this->session->getAbsoluteExpiresAt()->modify('+1 year')
        );
        $id = $this->session->getId()->toString();
        self::assertSame('Chrome', $store->descriptors([$id])[$id]->browser->value);
        self::assertSame([], $store->descriptors([]));
        $this->connection->executeStatement(
            'ALTER TABLE session_presentation ADD CONSTRAINT presentation_abort CHECK (FALSE) NOT VALID'
        );
        $connection = $this->container->get(Connection::class);
        $connection->beginTransaction();
        try {
            $store->capture(
                RefreshSessionId::generate(),
                new DeviceDescriptor(),
                $this->session->getAbsoluteExpiresAt()
            );
            self::fail('Injected metadata failure did not occur.');
        } catch (\Doctrine\DBAL\Exception) {
            self::assertSame(1, (int) $connection->fetchOne('SELECT 1'));
        } finally {
            $connection->rollBack();
        }
        $this->connection->executeStatement('ALTER TABLE session_presentation DROP CONSTRAINT presentation_abort');
        $this->connection->executeStatement(
            "UPDATE session_presentation SET expires_at = CURRENT_TIMESTAMP + INTERVAL '1 day'"
        );
        $this->connection->executeStatement(<<<'SQL'
UPDATE refresh_sessions SET idle_expires_at = CURRENT_TIMESTAMP + INTERVAL '1 day',
    absolute_expires_at = CURRENT_TIMESTAMP + INTERVAL '2 days'
SQL);
        self::assertSame(0, $store->prune(1));
        $this->connection->executeStatement(
            "UPDATE session_presentation SET expires_at = CURRENT_TIMESTAMP - INTERVAL '1 second'"
        );
        self::assertSame(1, $store->prune(1));
        self::assertSame(0, $store->prune(1));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
    }

    /**
     * Creates one ordinary permission-bearing identity without minting client authority
     */
    private function user(string $email): User
    {
        $now = $this->tokens->clock->now()->modify('-2 hours');
        $user = User::invite(UserId::generate(), EmailAddress::fromString($email), $now);
        $user->activate(
            PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_BCRYPT, ['cost' => 4])),
            $now
        );
        $user->assignRole(RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID), $now);
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(UserRepository::class)->add($user)
        );

        return $user;
    }

    /**
     * Persists one authoritative package session
     */
    private function addSession(User $user, DateTimeImmutable $at, bool $remembered = false): RefreshSession
    {
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $at,
            $at->modify('+1 day'),
            $at->modify('+2 days'),
            $user->getAuthenticationVersion(),
            $remembered
        );
        $this->connection->transactional(fn() => RefreshSessionRecords::insert($this->connection, $session));

        return $session;
    }

    /**
     * Builds a fresh non-HTTP provider through authoritative package resolution
     */
    private function provider(): CurrentPrincipalProvider
    {
        $context = new AuthenticationContext(
            $this->actor->getId(),
            $this->session->getId(),
            $this->actor->getAuthenticationVersion()
        );

        return new CurrentPrincipalProvider(
            new RequestAuthenticationContext($context),
            $this->container->get(AuthoritativePrincipalResolver::class)
        );
    }

    /**
     * Invokes actual HTTP routing without trusting body or cookie identity
     */
    private function request(string $query = '', bool $authenticated = true, string $body = ''): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'https://agent-os.test/api/v1/me/sessions'.$query
        );
        if ($authenticated) {
            $request = $request->withHeader('Authorization', 'Bearer '.$this->token);
        }
        $request->getBody()->write($body);

        return $this->app->handle($request);
    }
}
