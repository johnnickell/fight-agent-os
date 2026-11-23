<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Auth\LogoutAction;
use App\Adapter\Http\Action\Api\V1\Auth\RefreshAction;
use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\RefreshCookie;
use App\Adapter\Http\Api\V1\Auth\SessionResponseFailure;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionLifecycleResponder;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use DateInterval;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\RefreshCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionRevocationService;
use Fight\AccessControl\Application\AccessControl\RefreshSession\SessionLifecycle;
use Fight\AccessControl\Application\AccessControl\User\Authentication\TokenIssuer;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationTokenPolicy;
use Fight\AccessControl\Application\AccessControl\User\Security\RefreshResult;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\Error\Exception\ServiceUnavailable;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessClock;

/**
 * Protects browser outcomes at the direct package session boundary
 */
final class BrowserSessionLifecycleActionTest extends TestCase
{
    /**
     * Returns the same cookie deletion and generic success for present and absent server authority
     */
    #[DataProvider('currentOrUnknown')]
    public function testLogoutDoesNotDiscloseServerAuthority(bool $known): void
    {
        $clock = new AccessClock();
        $credential = RefreshCredential::fromString(str_repeat('a', 64));
        $tokenPolicy = AuthenticationTokenPolicy::starterDefaults(new DateInterval('PT5S'));
        $tokenIssuer = new TokenIssuer($this->createStub(TokenEncoder::class), $tokenPolicy);
        $sessions = $this->createMock(RefreshSessionRepository::class);
        if ($known) {
            $session = $tokenIssuer->startSession($this->activeUser($clock), $credential, $clock->time, false);
            $sessions->method('getByCredential')->willReturn($session);
            $sessions->expects(self::once())->method('replace')->with($session, self::callback(
                static fn(RefreshSession $next): bool => $next->isRevoked() && $next->getId()->equals($session->getId())
            ))->willReturn(true);
        } else {
            $sessions->method('getByCredential')->willReturn(null);
            $sessions->expects(self::never())->method('replace');
        }
        $users = $this->createStub(UserRepository::class);
        $lifecycle = $this->lifecycle($sessions, $users, $clock, $tokenPolicy, $tokenIssuer);

        $response = new LogoutAction($lifecycle, new RefreshCookie(), $this->responder($clock))
            ->handle($this->request('logout', $credential));

        self::assertSame(200, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"status":"success","data":null}', (string) $response->getBody());
        $this->assertAuthorityCleared($response);
    }

    /**
     * Supplies current and unknown authority to the same public logout outcome
     *
     * @return iterable<string, array{bool}>
     */
    public static function currentOrUnknown(): iterable
    {
        yield 'current' => [true];
        yield 'unknown' => [false];
    }

    /**
     * Keeps non-authoritative refresh generic when a lookup misses or the write fence refuses
     */
    #[DataProvider('currentOrUnknown')]
    public function testRefreshRefusalClearsAuthorityWithoutDisclosure(bool $known): void
    {
        $clock = new AccessClock();
        $credential = RefreshCredential::fromString(str_repeat('a', 64));
        $tokenPolicy = AuthenticationTokenPolicy::starterDefaults(new DateInterval('PT5S'));
        $tokenIssuer = new TokenIssuer($this->createStub(TokenEncoder::class), $tokenPolicy);
        $users = $this->createStub(UserRepository::class);
        $sessions = $this->createMock(RefreshSessionRepository::class);
        if ($known) {
            $user = $this->activeUser($clock);
            $users->method('getById')->willReturn($user);
            $sessions->method('getByCredential')->willReturn(
                $tokenIssuer->startSession($user, $credential, $clock->time, false)
            );
            $sessions->expects(self::once())->method('replace')->willThrowException(
                new RefreshSessionNotFoundException('Fixture-only write-fence diagnostic.')
            );
        } else {
            $sessions->method('getByCredential')->willReturn(null);
            $sessions->method('getByUsedCredential')->willReturn(null);
            $sessions->expects(self::never())->method('replace');
        }
        $lifecycle = $this->lifecycle($sessions, $users, $clock, $tokenPolicy, $tokenIssuer);

        $response = new RefreshAction($lifecycle, new RefreshCookie(), $this->responder($clock))
            ->handle($this->request('refresh', $credential));

        self::assertSame(401, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status":"fail","data":{"message":"Authentication required."}}',
            (string) $response->getBody()
        );
        $this->assertAuthorityCleared($response);
    }

    /**
     * Keeps browser authority cleared when session storage cannot confirm the outcome
     */
    #[DataProvider('operations')]
    public function testUnavailableStorage(string $operation): void
    {
        $clock = new AccessClock();
        $credential = RefreshCredential::fromString(str_repeat('a', 64));
        $policy = AuthenticationTokenPolicy::starterDefaults(new DateInterval('PT5S'));
        $repository = $this->createStub(RefreshSessionRepository::class);
        $repository->method('getByCredential')->willThrowException(new ServiceUnavailable('Private store detail.'));
        $lifecycle = $this->lifecycle(
            $repository,
            $this->createStub(UserRepository::class),
            $clock,
            $policy,
            new TokenIssuer($this->createStub(TokenEncoder::class), $policy)
        );
        $action = $operation === 'refresh' ? new RefreshAction(
            $lifecycle,
            new RefreshCookie(),
            $this->responder($clock)
        ) : new LogoutAction($lifecycle, new RefreshCookie(), $this->responder($clock));

        $response = $action->handle($this->request($operation, $credential));

        self::assertSame(503, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status":"error","message":"Service unavailable."}',
            (string) $response->getBody()
        );
        $this->assertAuthorityCleared($response);
    }

    /**
     * Supplies both browser session operations
     *
     * @return iterable<string, array{string}>
     */
    public static function operations(): iterable
    {
        yield 'refresh' => ['refresh'];
        yield 'logout' => ['logout'];
    }

    /**
     * Refuses refresh without any server lookup when the refresh cookie is absent
     */
    public function testRefreshWithoutCookie(): void
    {
        $clock = new AccessClock();
        $policy = AuthenticationTokenPolicy::starterDefaults(new DateInterval('PT5S'));
        $repository = $this->createMock(RefreshSessionRepository::class);
        $repository->expects(self::never())->method('getByCredential');
        $lifecycle = $this->lifecycle(
            $repository,
            $this->createStub(UserRepository::class),
            $clock,
            $policy,
            new TokenIssuer($this->createStub(TokenEncoder::class), $policy)
        );
        $response = new RefreshAction($lifecycle, new RefreshCookie(), $this->responder($clock))->handle(
            new ServerRequestFactory()->createServerRequest('POST', '/api/v1/auth/refresh')
        );
        self::assertSame(401, $response->getStatusCode());
        $this->assertAuthorityCleared($response);
    }

    /**
     * Returns ordinary access data and delivers replacement refresh material only in the refresh cookie
     */
    public function testRefreshRotationKeepsCsrfAndRefreshOutOfJson(): void
    {
        $clock = new AccessClock();
        $credential = RefreshCredential::fromString(str_repeat('a', 64));
        $tokenPolicy = AuthenticationTokenPolicy::starterDefaults(new DateInterval('PT5S'));
        $encoder = $this->createStub(TokenEncoder::class);
        $encoder->method('encode')->willReturn('fixture-access-token');
        $tokenIssuer = new TokenIssuer($encoder, $tokenPolicy);
        $user = $this->activeUser($clock);
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $session = $tokenIssuer->startSession($user, $credential, $clock->time, false);
        $sessions->method('getByCredential')->willReturn($session);
        $sessions->method('replace')->willReturn(true);
        $lifecycle = $this->lifecycle($sessions, $users, $clock, $tokenPolicy, $tokenIssuer);

        $response = new RefreshAction($lifecycle, new RefreshCookie(), $this->responder($clock))
            ->handle($this->request('refresh', $credential));

        self::assertSame(200, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(json_encode([
            'status' => 'success',
            'data'   => [
                'access_token' => 'fixture-access-token',
                'issued_at'    => $clock->time->getTimestamp(),
                'expires_at'   => $clock->time->getTimestamp() + 900
            ]
        ], JSON_THROW_ON_ERROR), (string) $response->getBody());
        self::assertSame([
            '__Secure-agent_os_refresh='.str_repeat('b', 64).'; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict'
        ], $response->getHeader('Set-Cookie'));
    }

    /**
     * Leaves the browser cookie alone when a competing refresh can still succeed
     */
    public function testRecoverableConflictDoesNotClearCookie(): void
    {
        $response = $this->responder(new AccessClock())->respond(RefreshResult::conflict());
        self::assertSame(409, $response->getStatusCode());
        self::assertSame([], $response->getHeader('Set-Cookie'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertJsonStringEqualsJsonString(
            '{"status":"fail","data":{"message":"State conflict."}}',
            (string) $response->getBody()
        );
    }

    /**
     * Preserves the deletion obligation if the initial response or diagnostic sink fails
     */
    #[DataProvider('responseFailures')]
    public function testRenderingFailureRetainsDeletion(string $outcome, int $failures, bool $sinkFails): void
    {
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->expects(self::exactly($failures + 1))->method('createResponse')->willReturnCallback(
            static function (int $status) use (&$failures): ResponseInterface {
                if ($failures-- > 0) {
                    throw new RuntimeException('private renderer failure');
                }

                return new ResponseFactory()->createResponse($status);
            }
        );
        $sink = $this->createMock(SafeHttpDiagnostics::class);
        $report = $sink->expects(self::once())->method('report');
        if ($sinkFails) {
            $report->willThrowException(new RuntimeException('private diagnostic failure'));
        }
        $responder = $this->responder(new AccessClock(), $factory, $sink);
        $response = match ($outcome) {
            'terminal' => $responder->terminal(),
            'logout' => $responder->loggedOut(),
            default => $responder->unavailable(new RuntimeException('private storage failure'))
        };

        self::assertSame(500, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status":"error","message":"Internal server error."}',
            (string) $response->getBody()
        );
        $this->assertAuthorityCleared($response);
    }

    /**
     * Supplies failures at each independent recovery boundary
     *
     * @return iterable<string, array{string, int, bool}>
     */
    public static function responseFailures(): iterable
    {
        yield 'terminal response' => ['terminal', 1, false];
        yield 'logout response' => ['logout', 1, false];
        yield 'recovery response' => ['unavailable', 1, false];
        yield 'diagnostic sink' => ['unavailable', 0, true];
    }

    /**
     * Signals the outer transport boundary when no cookie-clearing response can be built
     */
    public function testExhaustedRenderingSignalsDeletionObligation(): void
    {
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->expects(self::exactly(2))->method('createResponse')->willThrowException(
            new RuntimeException('private renderer failure')
        );
        $sink = $this->createMock(SafeHttpDiagnostics::class);
        $sink->expects(self::once())->method('report');
        $this->expectException(SessionResponseFailure::class);

        $this->responder(new AccessClock(), $factory, $sink)->unavailable(new RuntimeException('private store fault'));
    }

    /**
     * Runs actual package session decisions over isolated ports rather than imitating its lifecycle
     */
    private function lifecycle(
        RefreshSessionRepository $sessions,
        UserRepository $users,
        AccessClock $clock,
        AuthenticationTokenPolicy $tokenPolicy,
        TokenIssuer $tokenIssuer
    ): SessionLifecycle {
        $transaction = $this->createStub(TransactionalUnitOfWork::class);
        $transaction->method('commitTransactional')->willReturnCallback(static fn(callable $work): mixed => $work());
        $credentials = $this->createStub(RefreshCredentialGenerator::class);
        $credentials->method('generate')->willReturn(RefreshCredential::fromString(str_repeat('b', 64)));

        return new SessionLifecycle(
            $users,
            $sessions,
            new SessionRevocationService($sessions),
            $transaction,
            $clock,
            $credentials,
            $tokenIssuer,
            $tokenPolicy,
            $this->createStub(EventDispatcher::class)
        );
    }

    /**
     * Creates current package authority with no persistent fixture
     */
    private function activeUser(AccessClock $clock): User
    {
        $user = User::invite(UserId::generate(), EmailAddress::fromString('session@example.test'), $clock->time);
        $hash = password_hash('fixture-password', PASSWORD_BCRYPT, ['cost' => 4]);
        $user->activate(PasswordHash::fromString($hash), $clock->time);

        return $user;
    }

    /**
     * Supplies only the browser cookie selected by the action
     */
    private function request(string $operation, RefreshCredential $credential): ServerRequestInterface
    {
        return new ServerRequestFactory()->createServerRequest('POST', '/api/v1/auth/'.$operation)
            ->withHeader('Cookie', '__Secure-agent_os_refresh='.$credential->toString());
    }

    /**
     * Composes the real browser response and diagnostic boundary
     */
    private function responder(
        AccessClock $clock,
        ?ResponseFactoryInterface $factory = null,
        ?SafeHttpDiagnostics $sink = null
    ): SessionLifecycleResponder {
        $responses = new JSendResponseFactory($factory ?? new ResponseFactory(), new StreamFactory());

        return new SessionLifecycleResponder(
            new SessionResponder($responses, new BrowserSessionCookies($clock)),
            $responses,
            $sink ?? $this->createStub(SafeHttpDiagnostics::class),
            new SafeHttpErrorPolicy()
        );
    }

    /**
     * Checks deletion of both ambient credentials without weakening their scope
     */
    private function assertAuthorityCleared(ResponseInterface $response): void
    {
        $deletion = '=; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict;';
        $deletion .= ' Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0';
        self::assertSame([
            '__Secure-agent_os_refresh'.$deletion,
            '__Secure-agent_os_csrf'.$deletion
        ], $response->getHeader('Set-Cookie'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }
}
