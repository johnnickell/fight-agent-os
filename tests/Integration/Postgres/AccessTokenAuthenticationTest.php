<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Api\Auth\BearerCredential;
use App\Adapter\Http\Api\Failure\ApiFailureMapper;
use App\Adapter\Http\Middleware\Api\ApiFailureMiddleware;
use App\Adapter\Http\Middleware\Api\Auth\AccessTokenAuthentication;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Adapter\Persistence\Repository\PostgresRefreshSessionRepository;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\RefreshCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionRevocationService;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationTokenPolicy;
use Fight\AccessControl\Application\AccessControl\User\Service\LoginThrottle;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Auth\Security\PhpPasswordHasher;
use Fight\Common\Adapter\Auth\Security\PhpPasswordValidator;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessTokens;

/**
 * Proves real package issuance and request authentication against guarded PostgreSQL
 */
final class AccessTokenAuthenticationTest extends TestCase
{
    private Connection $connection;
    private PostgresUserRepository $users;
    private PostgresRefreshSessionRepository $sessions;
    private AccessTokens $fixture;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection(new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ])->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement('TRUNCATE users, refresh_sessions CASCADE');
        $this->users = new PostgresUserRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection),
            new AuthenticationAuthorityFences($this->connection)
        );
        $this->sessions = new PostgresRefreshSessionRepository(
            $this->connection,
            new \App\Adapter\Persistence\Locking\AuthenticationAuthorityFences($this->connection)
        );
        $this->fixture = new AccessTokens();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->connection->close();
    }

    /**
     * Authenticates a package login token then observes immediate persisted revocation
     */
    #[DataProvider('issuanceTimes')]
    public function testPackageLoginAndRequestScopedRevocation(?string $time = null): void
    {
        if ($time !== null) {
            $this->fixture->clock->time = new DateTimeImmutable($time);
        }

        $now = $this->fixture->clock->now();
        $hasher = new PhpPasswordHasher(PASSWORD_ARGON2ID);
        $hash = PasswordHash::fromString($hasher->hash('private-fixture-password'));
        $user = User::invite(UserId::generate(), EmailAddress::fromString('package-login@example.test'), $now);
        $user->activate($hash, $now);
        $this->connection->transactional(fn() => $this->users->add($user));
        $service = $this->service($hash);
        $tokens = $service->login('package-login@example.test', 'private-fixture-password');
        $token = $tokens->getAccessToken()->toString();
        $claims = $this->fixture->jwt->decode($token);
        self::assertSame($user->getId()->toString(), $claims['sub']);
        self::assertSame($tokens->getRefreshSessionId()->toString(), $claims['sid']);
        self::assertSame($user->getAuthenticationVersion(), $claims['auth_version']);
        self::assertSame('access', $claims['type']);
        self::assertSame($now->getTimestamp(), $claims['iat']);
        self::assertSame($claims['iat'], $claims['nbf']);
        self::assertSame(900, $claims['exp'] - $claims['iat']);
        self::assertSame($now->getTimestamp() + 900, $tokens->getAccessTokenExpiresAt()->getTimestamp());
        self::assertEquals($now->modify('+900 seconds'), $tokens->getAccessTokenExpiresAt());
        self::assertSame($now->format('u'), $tokens->getAccessTokenExpiresAt()->format('u'));
        self::assertTrue(new DateTimeImmutable('@'.$claims['exp']) <= $tokens->getAccessTokenExpiresAt());
        self::assertSame($claims['exp'], $tokens->getAccessTokenExpiresAt()->getTimestamp());
        self::assertSame($now, $this->fixture->clock->now());
        $session = $this->sessions->getById($tokens->getRefreshSessionId());
        self::assertNotNull($session);
        self::assertSame($claims['iat'], $session->getCreatedAt()->getTimestamp());
        self::assertEquals($now, $session->getCreatedAt());
        self::assertEquals($now->modify('+1 day'), $session->getIdleExpiresAt());
        self::assertEquals($now->modify('+2 days'), $session->getAbsoluteExpiresAt());
        $authentication = $this->authentication();
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->willReturnCallback(static function (
            ServerRequestInterface $request
        ) use (
            $user,
            $session
): ResponseInterface {
            $context = $request->getAttribute(AuthenticationContext::class);
            self::assertInstanceOf(AuthenticationContext::class, $context);
            self::assertTrue($context->getUserId()->equals($user->getId()));
            self::assertTrue($context->getRefreshSessionId()->equals($session->getId()));

            return new ResponseFactory()->createResponse(204);
        });
        $request = new ServerRequestFactory()->createServerRequest('GET', '/api/v1/owned')
            ->withHeader('Authorization', 'Bearer '.$token);
        self::assertSame(204, $authentication->process($request, $next)->getStatusCode());
        self::assertNull($request->getAttribute(AuthenticationContext::class));
        self::assertTrue($this->connection->transactional(
            fn() => $this->sessions->replace($session, $session->revoke())
        ));
        $this->assertUnauthorized($authentication, $request, $next);
    }

    /**
     * Exercises whole-second and representative raw system-clock precision
     *
     * @return iterable<string, array{string}>
     */
    public static function issuanceTimes(): iterable
    {
        yield 'whole-second' => ['2026-09-28T12:00:00.000000Z'];
        yield 'ordinary-subsecond' => ['2026-09-28T12:00:00.123456Z'];
        yield 'end-of-second' => ['2026-09-28T12:00:00.999999Z'];
        yield 'offset' => ['2026-09-28T14:00:00.999999+02:00'];
    }

    /**
     * Preserves exact fractional session deadlines across package refresh and Bearer resolution
     */
    #[DataProvider('sessionDeadlines')]
    public function testRefreshCannotResurrectExpiredFractionalSession(string $deadline, int $offset): void
    {
        $expiry = new DateTimeImmutable('2026-09-28T12:00:00.500000Z');
        $created = $expiry->modify('-1 hour');
        $now = $expiry->modify(sprintf('%+d microseconds', $offset));
        $this->fixture->clock->time = $now;
        $hash = PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_ARGON2ID));
        $user = User::invite(UserId::generate(), EmailAddress::fromString('refresh@example.test'), $created);
        $user->activate($hash, $created);
        $this->connection->transactional(fn() => $this->users->add($user));
        $credential = RefreshCredential::fromString(bin2hex(random_bytes(32)));
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            $credential,
            $created,
            $expiry,
            $deadline === 'absolute' ? $expiry : $expiry->modify('+2 days'),
            $user->getAuthenticationVersion(),
            false
        );
        $this->connection->transactional(fn() => RefreshSessionRecords::insert($this->connection, $session));
        $before = $this->connection->fetchAssociative('SELECT * FROM refresh_sessions WHERE id = ?', [
            $session->getId()->toString()
        ]);
        $denied = false;
        $context = null;
        try {
            $tokens = $this->service($hash)->refresh($credential->toString())->getTokenSet();
            try {
                $context = new AuthenticateAccessToken(
                    $this->fixture->jwt,
                    $this->users,
                    $this->sessions,
                    $this->fixture->clock
                )->authenticate($tokens->getAccessToken()->toString());
            } catch (AuthenticationRequired) {
                // Direct Bearer refusal must not hide a forbidden refresh rotation.
            }
        } catch (RefreshSessionNotFoundException) {
            $denied = true;
        }

        self::assertSame($offset >= 0, $denied, 'No grace at or after an exact persisted session deadline');
        $persisted = $this->sessions->getById($session->getId());
        self::assertNotNull($persisted);
        if ($offset >= 0) {
            self::assertNull($context);
            self::assertSame($before, $this->connection->fetchAssociative(
                'SELECT * FROM refresh_sessions WHERE id = ?',
                [$session->getId()->toString()]
            ));
        } else {
            self::assertInstanceOf(AuthenticationContext::class, $context);
            self::assertTrue($context->getRefreshSessionId()->equals($session->getId()));
            self::assertEquals($session->getAbsoluteExpiresAt(), $persisted->getAbsoluteExpiresAt());
            self::assertEquals($now, $persisted->getLastActivityAt());
            $idle = $now->modify('+1 day');
            self::assertEquals(
                $idle < $session->getAbsoluteExpiresAt() ? $idle : $session->getAbsoluteExpiresAt(),
                $persisted->getIdleExpiresAt()
            );
        }
    }

    /**
     * Supplies immediately before, exact and after both fractional hard deadlines
     *
     * @return iterable<string, array{string, int}>
     */
    public static function sessionDeadlines(): iterable
    {
        foreach (['idle', 'absolute'] as $deadline) {
            foreach ([-1, 0, 1, 400000] as $offset) {
                yield $deadline.' '.$offset => [$deadline, $offset];
            }
        }
    }

    /**
     * Contains token, claim and database markers at the real HTTP failure boundary
     */
    public function testHttpFailureRedaction(): void
    {
        $claims = $this->fixture->claims();
        $claims['private'] = 'PRIVATE_CLAIM_SQL_KEY_MARKER';
        $bad = $this->fixture->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        $request = new ServerRequestFactory()->createServerRequest('GET', '/api/v1/owned')
            ->withHeader('Authorization', 'Bearer '.$bad);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->assertUnauthorized($this->authentication(), $request, $next);
        $this->assertUnauthorized($this->authentication(), $request->withHeader('Authorization', [
            'Bearer '.$bad, 'Bearer TOKEN_MARKER'
        ]), $next);
        $this->assertUnauthorized($this->authentication(), $request->withUri(
            $request->getUri()->withQuery('access_token=TOKEN_MARKER')
        ), $next);

        unset($claims['private']);
        $valid = $this->fixture->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willThrowException(new \RuntimeException('SQL TOKEN KEY STACK PRIVATE MARKER'));
        $authentication = new AccessTokenAuthentication(new BearerCredential(), new AuthenticateAccessToken(
            $this->fixture->jwt,
            $users,
            $this->sessions,
            $this->fixture->clock
        ));
        $this->assertUnauthorized($authentication, $request->withHeader('Authorization', 'Bearer '.$valid), $next);
    }

    /**
     * Rejects signed stale authority using freshly persisted package state
     */
    #[DataProvider('stateCases')]
    public function testPersistedStateMatrix(string $case): void
    {
        $now = $this->fixture->clock->now();
        $user = User::invite(
            UserId::generate(),
            EmailAddress::fromString('state@example.test'),
            $now->modify('-1 hour')
        );
        if ($case !== 'pending') {
            $user->activate(
                PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_BCRYPT)),
                $now->modify('-1 hour')
            );
        }

        $version = max(1, $user->getAuthenticationVersion());
        if ($case === 'disabled') {
            $user->disable($now);
        } elseif ($case === 'deleted') {
            $user->delete($now);
        } elseif ($case === 'stale-user') {
            $user->changePassword(
                PasswordHash::fromString((string) password_hash('replacement', PASSWORD_BCRYPT)),
                $now
            );
        }

        $this->connection->transactional(fn() => $this->users->add($user));
        $owner = $user->getId();
        if ($case === 'wrong-owner') {
            $other = User::invite(UserId::generate(), EmailAddress::fromString('other@example.test'), $now);
            $this->connection->transactional(fn() => $this->users->add($other));
            $owner = $other->getId();
        }

        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $owner,
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $case === 'predates' ? $now : $now->modify('-1 hour'),
            $case === 'idle' || $case === 'absolute' ? $now : $now->modify('+1 day'),
            $case === 'absolute' ? $now : $now->modify('+2 days'),
            $case === 'stale-session' ? $version + 1 : $version,
            false
        );
        if ($case === 'revoked') {
            $session = $session->revoke();
        }

        $this->connection->transactional(fn() => RefreshSessionRecords::insert($this->connection, $session));
        $claims = $this->fixture->claims();
        $claims['sub'] = $user->getId()->toString();
        $claims['sid'] = $session->getId()->toString();
        $claims['auth_version'] = $version;
        if ($case === 'missing-user') {
            $claims['sub'] = UserId::generate()->toString();
        } elseif ($case === 'missing-session') {
            $claims['sid'] = RefreshSessionId::generate()->toString();
        } elseif ($case === 'predates') {
            // Move issuance before a newly created session while preserving a valid JWT lifetime.
            $claims['iat'] -= 1;
            $claims['nbf'] -= 1;
            $claims['exp'] -= 1;
        }

        $request = new ServerRequestFactory()->createServerRequest('GET', '/api/v1/owned')
            ->withHeader('Authorization', 'Bearer '.$this->fixture->signed(json_encode($claims, JSON_THROW_ON_ERROR)));
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->assertUnauthorized($this->authentication(), $request, $next);
    }

    /**
     * Enumerates authoritative PostgreSQL refusal conditions
     *
     * @return iterable<string, array{string}>
     */
    public static function stateCases(): iterable
    {
        $cases = [
            'pending', 'disabled', 'deleted', 'stale-user', 'stale-session', 'wrong-owner',
            'idle', 'absolute', 'revoked', 'missing-user', 'missing-session', 'predates'
        ];
        foreach ($cases as $case) {
            yield $case => [$case];
        }
    }

    /**
     * Composes real package authentication with owned token and persistence adapters
     */
    private function service(PasswordHash $hash): AuthenticationService
    {
        $orm = ORMSetup::createAttributeMetadataConfiguration([], true);
        $orm->enableNativeLazyObjects(true);
        $throttle = $this->createStub(LoginThrottle::class);
        $throttle->method('allows')->willReturn(true);
        $generator = $this->createStub(RefreshCredentialGenerator::class);
        $generator->method('generate')->willReturn(RefreshCredential::fromString(bin2hex(random_bytes(32))));

        return new AuthenticationService(
            $this->users,
            $this->createStub(ActivationGrantRepository::class),
            $this->sessions,
            new SessionRevocationService($this->sessions),
            new DoctrineTransactionalUnitOfWork(new EntityManager($this->connection, $orm)),
            $this->fixture->clock,
            $throttle,
            $generator,
            new PhpPasswordHasher(PASSWORD_ARGON2ID),
            new PhpPasswordValidator(PASSWORD_ARGON2ID),
            $this->fixture->jwt,
            new AuthenticationTokenPolicy(
                new DateInterval('PT15M'),
                new DateInterval('P1D'),
                new DateInterval('P2D'),
                new DateInterval('P30D'),
                new DateInterval('P1Y'),
                new DateInterval('PT5S')
            ),
            $hash,
            $this->createStub(EventDispatcher::class),
            $this->createStub(PasswordResetGrantRepository::class),
            $this->createStub(AuditEvidenceRepository::class),
            $this->createStub(EmailChangeGrantRepository::class)
        );
    }

    /**
     * Creates the actual authentication middleware without registering a synthetic endpoint
     */
    private function authentication(): AccessTokenAuthentication
    {
        return new AccessTokenAuthentication(new BearerCredential(), new AuthenticateAccessToken(
            $this->fixture->jwt,
            $this->users,
            $this->sessions,
            $this->fixture->clock
        ));
    }

    /**
     * Proves correlated sanitized HTTP failures and absence of sensitive log events
     */
    private function assertUnauthorized(
        AccessTokenAuthentication $authentication,
        ServerRequestInterface $request,
        RequestHandlerInterface $next
    ): void {
        $logs = new TestHandler();
        $failure = new ApiFailureMiddleware(
            new ApiFailureMapper(),
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory()),
            new Logger('auth-test', [$logs])
        );
        $pipeline = new class ($authentication, $next) implements RequestHandlerInterface {
            /**
             * Constructs RequestHandler
             */
            public function __construct(
                private readonly AccessTokenAuthentication $authentication,
                private readonly RequestHandlerInterface $next
            ) {
            }

            /**
             * @inheritDoc
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->authentication->process($request, $this->next);
            }
        };
        $response = $failure->process($request, $pipeline);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            ['status' => 'error', 'message' => 'Unauthorized.'],
            json_decode((string) $response->getBody(), true, 8, JSON_THROW_ON_ERROR)
        );
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $response->getHeaderLine('X-Correlation-ID'));
        self::assertSame([], $logs->getRecords());
    }
}
