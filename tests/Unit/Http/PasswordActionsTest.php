<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Auth\ActivateInvitationAction;
use App\Adapter\Http\Action\Api\V1\Auth\ChangePasswordAction;
use App\Adapter\Http\Action\Api\V1\Auth\CompletePasswordResetAction;
use App\Adapter\Http\Action\Api\V1\Auth\LoginAction;
use App\Adapter\Http\Action\Api\V1\Auth\RequestPasswordResetAction;
use App\Adapter\Http\Api\Auth\RequestAuthenticationContext;
use App\Adapter\Http\Api\Failure\PublicFailureProjector;
use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\CaptureSessionPresentation;
use App\Adapter\Http\Api\V1\Auth\PasswordMutationUnconfirmed;
use App\Adapter\Http\Api\V1\Auth\RefreshCookie;
use App\Adapter\Http\Api\V1\Auth\ResetBrowserSession;
use App\Adapter\Http\Api\V1\Auth\ResetResponseTiming;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Responder\Api\V1\Auth\PasswordChangeResponder;
use App\Adapter\Http\Responder\Api\V1\Auth\ResetCompletionResponder;
use App\Adapter\Http\Responder\Api\V1\Auth\ResetRequestResponder;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionResponder;
use App\Application\Security\Password\CompromisedPasswordChecker;
use App\Application\Security\Password\PasswordRejected;
use App\Application\Security\Password\PasswordSetting;
use App\Application\Security\Session\SessionPresentation;
use App\Domain\Security\Password\PasswordRejection;
use App\Domain\Security\Password\PasswordStructure;
use DateInterval;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\RefreshCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionRevocationService;
use Fight\AccessControl\Application\AccessControl\User\Authentication\TokenIssuer;
use Fight\AccessControl\Application\AccessControl\User\Authentication\UserAuthentication;
use Fight\AccessControl\Application\AccessControl\User\PasswordManagement;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationTokenPolicy;
use Fight\AccessControl\Application\AccessControl\User\Service\LoginThrottle;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\RequestPasswordReset;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessClock;
use Throwable;

/**
 * Protects mapping and presentation at the direct password capability boundaries
 */
final class PasswordActionsTest extends TestCase
{
    private const string PASSWORD = ' New-Fixture-Ω42! ';
    private const string CURRENT = ' Current-Fixture-Ω42! ';
    private const string CREDENTIAL = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string REFRESH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    /**
     * Preserves exact password bytes and chooses ordinary session and device presentation after activation
     */
    public function testActivationMapsAcceptedInputToOrdinarySession(): void
    {
        $clock = new AccessClock();
        $user = $this->user($clock, false);
        $users = $this->createMock(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $users->expects(self::once())->method('replaceAuthenticationAuthorityAndAddRefreshSession')->with(
            $user,
            self::isInstanceOf(User::class),
            self::isInstanceOf(RefreshSession::class)
        )->willReturn(true);
        $grant = ActivationGrant::issue(
            ActivationOrigin::PRIVATE_INVITATION,
            $user->getId(),
            ActivationCredential::fromString(self::CREDENTIAL),
            $clock->time,
            $clock->time->modify('+1 hour'),
            $user->getEmail(),
            'fixture-ciphertext'
        );
        $grants = $this->createMock(ActivationGrantRepository::class);
        $grants->method('getLatestByUserId')->willReturn($grant);
        $grants->expects(self::once())->method('replace')->willReturn(true);
        $presentation = $this->createMock(SessionPresentation::class);
        $presentation->expects(self::once())->method('capture');
        $action = $this->activation($clock, $users, $grants, $this->transaction(), $this->hasher(), $presentation);

        $response = $action->handle($this->request($user->getId()));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('fixture-access-token', (string) $response->getBody());
        self::assertStringNotContainsString(self::REFRESH, (string) $response->getBody());
        $cookies = $response->getHeader('Set-Cookie');
        self::assertCount(2, $cookies);
        $scope = '; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict';
        self::assertSame('__Secure-agent_os_refresh='.self::REFRESH.$scope, $cookies[0]);
        self::assertStringStartsWith('__Secure-agent_os_csrf=', $cookies[1]);
        self::assertStringEndsWith($scope, $cookies[1]);
    }

    /**
     * Passes unmodified password bytes and remembered-session intent through login
     */
    public function testLoginReturnsRememberedSessionWithoutRefreshInJson(): void
    {
        $clock = new AccessClock();
        $user = $this->user($clock);
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('getByEmail')->with($user->getEmail())->willReturn($user);
        $users->expects(self::once())->method('replaceAuthenticationAuthorityAndAddRefreshSession')->with(
            $user,
            self::isInstanceOf(User::class),
            self::isInstanceOf(RefreshSession::class)
        )->willReturn(true);
        $throttle = $this->createMock(LoginThrottle::class);
        $throttle->expects(self::once())->method('allows')->with($user->getEmail())->willReturn(true);
        $validator = $this->createMock(PasswordValidator::class);
        $validator->expects(self::once())->method('validate')->with(self::CURRENT, $this->hash()->toString())
            ->willReturn(true);
        $presentation = $this->createMock(SessionPresentation::class);
        $presentation->expects(self::once())->method('capture');
        $action = new LoginAction(
            $this->authentication(
                $clock,
                $users,
                $this->createStub(ActivationGrantRepository::class),
                $this->transaction(),
                $this->createStub(PasswordHasher::class),
                $throttle,
                $validator
            ),
            new SessionResponder($this->responses(), new BrowserSessionCookies($clock)),
            new CaptureSessionPresentation($presentation)
        );
        $request = $this->request($user->getId())->withAttribute(JsonBody::class, [
            'email' => $user->getEmail()->toString(), 'password' => self::CURRENT, 'remember' => true
        ]);

        $response = $action->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('fixture-access-token', (string) $response->getBody());
        self::assertStringNotContainsString(self::REFRESH, (string) $response->getBody());
        self::assertStringEndsWith('; Expires=Wed, 28 Oct 2026 12:00:00 GMT', $response->getHeader('Set-Cookie')[0]);
    }

    /**
     * Uses only the trusted principal and passes current proof and replacement bytes unchanged
     */
    public function testChangeMapsCurrentOwnerAndClearsBrowserAuthority(): void
    {
        $clock = new AccessClock();
        $user = $this->user($clock);
        $users = $this->createMock(UserRepository::class);
        $users->method('getById')->with($user->getId())->willReturn($user);
        $users->expects(self::once())->method('replaceAuthenticationAuthority')->with($user)->willReturn(true);
        $sessions = $this->sessions($clock, $user);
        $validator = $this->createMock(PasswordValidator::class);
        $validator->expects(self::once())->method('validate')->with(self::CURRENT, $this->hash()->toString())
            ->willReturn(true);
        $action = $this->change($clock, $this->management(
            $clock,
            $users,
            $sessions,
            $this->createStub(PasswordResetGrantRepository::class),
            $this->transaction(),
            $this->hasher(),
            $validator
        ));
        $request = $this->request(UserId::generate())->withAttribute(
            CurrentPrincipalProvider::class,
            $this->principal($clock, $user, $users, $sessions)
        );

        $response = $action->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('access_token', (string) $response->getBody());
        self::assertCount(2, $response->getHeader('Set-Cookie'));
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            self::assertStringContainsString('Max-Age=0', $cookie);
        }
    }

    /**
     * Confines ambient cookie use to cleanup after a successful proof-based reset
     */
    #[DataProvider('browserOwnership')]
    public function testResetMapsProofWithoutIssuingAuthority(bool $matching): void
    {
        $clock = new AccessClock();
        $user = $this->user($clock);
        $users = $this->createMock(UserRepository::class);
        $users->method('getById')->with($user->getId())->willReturn($user);
        $users->method('validateRecoveryAuthority')->willReturn(true);
        $users->expects(self::once())->method('replaceAuthenticationAuthority')->with($user)->willReturn(true);
        $sessions = $this->sessions($clock, $matching ? $user : $this->user($clock));
        $grant = PasswordResetGrant::issue(
            $user->getId(),
            PasswordResetCredential::fromString(self::CREDENTIAL),
            $clock->time,
            $clock->time->modify('+1 hour'),
            $user->getEmail(),
            'fixture-ciphertext',
            $user->getRecoveryAuthority()
        );
        $grants = $this->createMock(PasswordResetGrantRepository::class);
        $grants->method('getLatestByUserId')->with($user->getId())->willReturn($grant);
        $grants->expects(self::once())->method('replace')->willReturn(true);
        $action = $this->reset($clock, $sessions, $this->management(
            $clock,
            $users,
            $sessions,
            $grants,
            $this->transaction(),
            $this->hasher(),
            $this->createStub(PasswordValidator::class)
        ));
        $response = $action->handle($this->request($user->getId())
            ->withHeader('Cookie', '__Secure-agent_os_refresh='.self::REFRESH));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('access_token', (string) $response->getBody());
        self::assertCount($matching ? 2 : 0, $response->getHeader('Set-Cookie'));
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            self::assertStringContainsString('Max-Age=0', $cookie);
        }
    }

    /**
     * Supplies target and unrelated browser authority
     *
     * @return iterable<string, array{bool}>
     */
    public static function browserOwnership(): iterable
    {
        yield 'matching' => [true];
        yield 'unrelated' => [false];
    }

    /**
     * Keeps refusal, post-commit uncertainty and internal failure separate without retaining private causes
     *
     * @phpstan-param class-string<Throwable> $expected
     */
    #[DataProvider('failures')]
    public function testMutationFailureMapping(
        string $operation,
        Throwable $fault,
        string $expected,
        string $message
    ): void {
        $clock = new AccessClock();
        $user = $this->user($clock);
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $sessions = $this->sessions($clock, $user);
        $tx = $this->createMock(TransactionalUnitOfWork::class);
        $tx->expects(self::once())->method('commitTransactional')->willThrowException($fault);
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::never())->method('hash');
        $management = $this->management(
            $clock,
            $users,
            $sessions,
            $this->createStub(PasswordResetGrantRepository::class),
            $tx,
            $hasher,
            $this->createStub(PasswordValidator::class)
        );
        $action = match ($operation) {
            'activate' => $this->activation(
                $clock,
                $users,
                $this->createStub(ActivationGrantRepository::class),
                $tx,
                $hasher,
                $this->createStub(SessionPresentation::class)
            ),
            'change' => $this->change($clock, $management),
            default => $this->reset($clock, $sessions, $management)
        };
        try {
            $action->handle($this->request($user->getId())->withAttribute(
                CurrentPrincipalProvider::class,
                $this->principal($clock, $user, $users, $sessions)
            ));
            self::fail('The action unexpectedly returned success.');
        } catch (Throwable $failure) {
            self::assertSame($expected, $failure::class);
            self::assertSame($message, $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }

    /**
     * Supplies deliberate package refusals and synthetic boundary failures
     *
     * @return iterable<string, array{string, Throwable, class-string<Throwable>, string}>
     */
    public static function failures(): iterable
    {
        $labels = ['activate' => 'Activation', 'change' => 'Password change', 'reset' => 'Password reset'];
        foreach ($labels as $op => $label) {
            yield $op.' refusal' => [$op, new AuthenticationRequired('private-fixture'), StateConflict::class, ''];
            yield $op.' unconfirmed' => [
                $op, new EventDispatchFailed([]), PasswordMutationUnconfirmed::class,
                'Password mutation event dispatch failed; do not replay the request.'
            ];
            yield $op.' internal' => [
                $op, new RuntimeException('private-fixture'), RuntimeException::class,
                $label.' outcome could not be confirmed.'
            ];
        }
        yield 'activation contention' => ['activate', new StateConflict('private-fixture'), StateConflict::class, ''];
    }

    /**
     * Rejects missing trusted authority before checking password policy or dispatching
     */
    public function testChangeRequiresTrustedPrincipal(): void
    {
        $clock = new AccessClock();
        $tx = $this->createMock(TransactionalUnitOfWork::class);
        $tx->expects(self::never())->method('commitTransactional');
        $action = $this->change($clock, $this->management(
            $clock,
            $this->createStub(UserRepository::class),
            $this->createStub(RefreshSessionRepository::class),
            $this->createStub(PasswordResetGrantRepository::class),
            $tx,
            $this->createStub(PasswordHasher::class),
            $this->createStub(PasswordValidator::class)
        ));
        $this->expectException(AuthenticationRequired::class);
        $action->handle($this->request(UserId::generate()));
    }

    /**
     * Keeps policy guidance independent of a diagnostic string and unrelated internal failures
     */
    public function testPolicyProjection(): void
    {
        $projector = new PublicFailureProjector();
        $failure = $projector->project(new PasswordRejected(PasswordRejection::COMMON_PASSWORD), 422);
        self::assertNotNull($failure);
        self::assertSame(['fields' => ['body.password' => [PasswordStructure::GUIDANCE]]], $failure->data);
        self::assertNull($projector->project(new RuntimeException('private-fixture'), 500));
    }

    /**
     * Dispatches the typed canonical email and returns an acknowledgement, not a delivery result
     */
    public function testResetRequestDispatchesCanonicalEmail(): void
    {
        $bus = $this->createMock(CommandBus::class);
        $bus->expects(self::once())->method('execute')->with(self::equalTo(
            new RequestPasswordReset(EmailAddress::fromString('fixture@example.test'))
        ));
        $timing = new ResetResponseTiming(static fn(): int => 0, static function (int $wait): void {
            self::assertSame(250_000_000, $wait);
        });
        $request = new ServerRequestFactory()->createServerRequest('POST', '/')->withAttribute(
            JsonBody::class,
            ['email' => 'fixture@example.test']
        );
        $response = new RequestPasswordResetAction($bus, $timing, new ResetRequestResponder($this->responses()))
            ->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $response->getHeader('Set-Cookie'));
        self::assertStringNotContainsString('fixture@example.test', (string) $response->getBody());
    }

    /**
     * Composes activation over in-memory ports instead of replacing the concrete package owner
     */
    private function activation(
        AccessClock $clock,
        UserRepository $users,
        ActivationGrantRepository $grants,
        TransactionalUnitOfWork $tx,
        PasswordHasher $hasher,
        SessionPresentation $presentation
    ): ActivateInvitationAction {
        return new ActivateInvitationAction(
            $this->policy(),
            $this->authentication(
                $clock,
                $users,
                $grants,
                $tx,
                $hasher,
                $this->createStub(LoginThrottle::class),
                $this->createStub(PasswordValidator::class)
            ),
            new SessionResponder($this->responses(), new BrowserSessionCookies($clock)),
            new CaptureSessionPresentation($presentation)
        );
    }

    /**
     * Runs the package authentication owner over isolated dependencies
     */
    private function authentication(
        AccessClock $clock,
        UserRepository $users,
        ActivationGrantRepository $grants,
        TransactionalUnitOfWork $tx,
        PasswordHasher $hasher,
        LoginThrottle $throttle,
        PasswordValidator $validator
    ): UserAuthentication {
        $credentials = $this->createStub(RefreshCredentialGenerator::class);
        $credentials->method('generate')->willReturn(RefreshCredential::fromString(self::REFRESH));

        return new UserAuthentication(
            $users,
            $grants,
            $tx,
            $clock,
            $throttle,
            $credentials,
            $hasher,
            $validator,
            $this->issuer(),
            $this->hash(),
            $this->createStub(EventDispatcher::class)
        );
    }

    /**
     * Composes the real change response boundary
     */
    private function change(AccessClock $clock, PasswordManagement $owner): ChangePasswordAction
    {
        return new ChangePasswordAction($this->policy(), $owner, new PasswordChangeResponder(
            $this->responses(),
            new BrowserSessionCookies($clock)
        ));
    }

    /**
     * Composes the real reset response boundary
     */
    private function reset(
        AccessClock $clock,
        RefreshSessionRepository $sessions,
        PasswordManagement $owner
    ): CompletePasswordResetAction {
        return new CompletePasswordResetAction(
            $this->policy(),
            $owner,
            new ResetBrowserSession(new RefreshCookie(), $sessions),
            new ResetCompletionResponder($this->responses(), new BrowserSessionCookies($clock))
        );
    }

    /**
     * Runs the installed password owner over isolated collaborators
     */
    private function management(
        AccessClock $clock,
        UserRepository $users,
        RefreshSessionRepository $sessions,
        PasswordResetGrantRepository $grants,
        TransactionalUnitOfWork $tx,
        PasswordHasher $hasher,
        PasswordValidator $validator
    ): PasswordManagement {
        return new PasswordManagement(
            $users,
            $grants,
            new SessionRevocationService($sessions),
            $tx,
            $clock,
            $hasher,
            $validator,
            $this->hash(),
            $this->createStub(EventDispatcher::class)
        );
    }

    /**
     * Creates an authenticated provider backed by the same isolated authority as the action
     */
    private function principal(
        AccessClock $clock,
        User $user,
        UserRepository $users,
        RefreshSessionRepository $sessions
    ): CurrentPrincipalProvider {
        $session = $sessions->getByCredential(RefreshCredential::fromString(self::REFRESH));
        self::assertNotNull($session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturn([]);

        return new CurrentPrincipalProvider(new RequestAuthenticationContext(new AuthenticationContext(
            $user->getId(),
            $session->getId(),
            $user->getAuthenticationVersion()
        )), new AuthoritativePrincipalResolver(
            $users,
            $sessions,
            $roles,
            $this->createStub(PermissionRepository::class),
            $clock
        ));
    }

    /**
     * Supplies an ordinary fixture browser session to both lookup paths
     */
    private function sessions(AccessClock $clock, User $user): RefreshSessionRepository
    {
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $session = $this->issuer()->startSession(
            $user,
            RefreshCredential::fromString(self::REFRESH),
            $clock->time,
            false
        );
        $sessions->method('getById')->willReturn($session);
        $sessions->method('getByCredential')->willReturn($session);

        return $sessions;
    }

    /**
     * Builds a package token issuer without signing or network access
     */
    private function issuer(): TokenIssuer
    {
        $encoder = $this->createStub(TokenEncoder::class);
        $encoder->method('encode')->willReturn('fixture-access-token');

        return new TokenIssuer($encoder, AuthenticationTokenPolicy::starterDefaults(new DateInterval('PT5S')));
    }

    /**
     * Admits a structurally valid fixture without consulting an external corpus
     */
    private function policy(): PasswordSetting
    {
        $checker = $this->createStub(CompromisedPasswordChecker::class);
        $checker->method('isCompromised')->willReturn(false);

        return new PasswordSetting(new PasswordStructure(), $checker);
    }

    /**
     * Executes the package callback in memory without asserting persistence guarantees
     */
    private function transaction(): TransactionalUnitOfWork
    {
        $tx = $this->createStub(TransactionalUnitOfWork::class);
        $tx->method('commitTransactional')->willReturnCallback(static fn(callable $work): mixed => $work());

        return $tx;
    }

    /**
     * Checks exact accepted password bytes at the package hashing port
     */
    private function hasher(): PasswordHasher
    {
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::once())->method('hash')->with(self::PASSWORD)->willReturn($this->hash()->toString());

        return $hasher;
    }

    /**
     * Supplies a fixed hash of a synthetic test value
     */
    private function hash(): PasswordHash
    {
        return PasswordHash::fromString('$2y$04$abcdefghijklmnopqrstuuDZMv3fzpYoQ48dkTZBRyake8IRMpO2W');
    }

    /**
     * Creates pending or active isolated package authority
     */
    private function user(AccessClock $clock, bool $active = true): User
    {
        $user = User::invite(UserId::generate(), EmailAddress::fromString('fixture@example.test'), $clock->time);
        if ($active) {
            $user->activate($this->hash(), $clock->time);
        }

        return $user;
    }

    /**
     * Supplies validated in-memory input without testing framework metadata
     */
    private function request(UserId $user): ServerRequestInterface
    {
        return new ServerRequestFactory()->createServerRequest('POST', '/')->withAttribute(JsonBody::class, [
            'user_id'               => $user->toString(),
            'credential'            => self::CREDENTIAL,
            'current_password'      => self::CURRENT,
            'password'              => self::PASSWORD,
            'password_confirmation' => self::PASSWORD
        ]);
    }

    /**
     * Shares the real JSend response boundary across the isolated actions
     */
    private function responses(): JSendResponseFactory
    {
        return new JSendResponseFactory(new ResponseFactory(), new StreamFactory());
    }
}
