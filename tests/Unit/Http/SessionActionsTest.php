<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Sessions\ListOwnedSessionsAction;
use App\Adapter\Http\Action\Api\V1\Sessions\ListUserSessionsAction;
use App\Adapter\Http\Action\Api\V1\Sessions\RevokeOwnedSessionAction;
use App\Adapter\Http\Action\Api\V1\Sessions\RevokeUserSessionAction;
use App\Adapter\Http\Api\Auth\RequestAuthenticationContext;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Responder\Api\V1\Sessions\OwnedSessionsResponder;
use App\Adapter\Http\Responder\Api\V1\Sessions\SessionRevocationResponder;
use App\Application\Security\Session\SessionPage;
use App\Domain\Security\Session\Query\ListOwnedSessions;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Command\RevokeSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\ListActiveSessions;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;
use Tests\Support\AccessClock;
use Throwable;

/**
 * Protects trusted session action inputs and safe responses after route permission admission
 */
final class SessionActionsTest extends TestCase
{
    private AccessClock $clock;
    private User $actor;
    private RefreshSession $current;
    private CurrentPrincipalProvider $provider;
    private JSendResponseFactory $responses;

    /**
     * Supplies a current principal using isolated authoritative records
     */
    protected function setUp(): void
    {
        $this->clock = new AccessClock();
        $now = $this->clock->now();
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('reader@example.test'), $now);
        $this->actor->activate(PasswordHash::fromString(password_hash('fixture-password', PASSWORD_BCRYPT)), $now);
        $this->current = $this->session(RefreshSessionId::generate(), $this->actor->getId());
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($this->actor);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturn($this->current);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturn([]);
        $this->provider = new CurrentPrincipalProvider(new RequestAuthenticationContext(new AuthenticationContext(
            $this->actor->getId(),
            $this->current->getId(),
            $this->actor->getAuthenticationVersion()
        )), new AuthoritativePrincipalResolver(
            $users,
            $sessions,
            $roles,
            $this->createStub(PermissionRepository::class),
            $this->clock
        ));
        $this->responses = new JSendResponseFactory(new ResponseFactory(), new StreamFactory());
    }

    /**
     * Maps bounded pagination with only the request principal's identity and current session
     */
    public function testOwnedPage(): void
    {
        $records = ArrayList::of(SessionView::class)->replace([SessionView::fromSession(
            $this->current,
            $this->current->getId()
        )]);
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::once())->method('fetch')->with(self::callback(
            fn(ListOwnedSessions $query): bool => $query->userId->equals($this->actor->getId())
                && $query->currentSessionId->equals($this->current->getId()) && $query->page === 2
                && $query->perPage === 1
        ))->willReturn(new SessionPage(new ResultSet(2, 1, 2, $records)));
        $action = new ListOwnedSessionsAction($queries, new OwnedSessionsResponder($this->responses));
        $request = $this->request()->withAttribute(QueryString::class, ['page' => '2', 'per_page' => '1']);
        $response = $action->handle($request);
        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(2, $data['page']);
        self::assertSame(1, $data['per_page']);
        self::assertSame($this->current->getId()->toString(), $data['records'][0]['session_id']);
        self::assertTrue($data['records'][0]['current']);
    }

    /**
     * Rejects reads without trusted request authority before querying sessions
     */
    #[DataProvider('readScopes')]
    public function testListRequiresPrincipal(bool $administrative): void
    {
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::never())->method('fetch');
        $responder = new OwnedSessionsResponder($this->responses);
        $action = $administrative ? new ListUserSessionsAction($queries, $responder) : new ListOwnedSessionsAction(
            $queries,
            $responder
        );
        $this->expectException(AuthenticationRequired::class);
        $action->handle($this->request()->withoutAttribute(CurrentPrincipalProvider::class));
    }

    /**
     * Supplies the two distinct session read entries
     *
     * @return iterable<array{bool}>
     */
    public static function readScopes(): iterable
    {
        yield [false];
        yield [true];
    }

    /**
     * Presents a package-provided administrative page under the existing response contract
     */
    public function testAdministrativePage(): void
    {
        $target = UserId::generate();
        $session = $this->session(RefreshSessionId::generate(), $target);
        $view = SessionView::fromSession($session, $this->current->getId());
        $records = ArrayList::of(SessionView::class)->replace([$view]);
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::once())->method('fetch')->with(self::callback(
            fn(ListActiveSessions $query): bool => $query->getActorId()->equals($this->actor->getId())
                && $query->getUserId()->equals($target)
                && $query->getCurrentSessionId()->equals($this->current->getId())
        ))->willReturn(new ResultSet(1, 25, 1, $records));
        $action = new ListUserSessionsAction($queries, new OwnedSessionsResponder($this->responses));
        $response = $action->handle($this->request('userId', $target->toString()));
        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame($session->getId()->toString(), $data['records'][0]['session_id']);
        self::assertFalse($data['records'][0]['current']);
        self::assertSame('unknown', $data['records'][0]['device_class']);
    }

    /**
     * Preserves administrative target admission and package denial mapping
     */
    #[DataProvider('administrativeRefusals')]
    public function testAdministrativeRefusal(string $case, Throwable $exception): void
    {
        $queries = $this->createMock(QueryBus::class);
        if ($case === 'denied') {
            $queries->expects(self::once())->method('fetch')->willThrowException(
                new SessionAdministrationAuthorizationException()
            );
        } else {
            $queries->expects(self::never())->method('fetch');
        }
        $value = match ($case) {
            'invalid' => 'invalid',
            'self'    => $this->actor->getId()->toString(),
            default   => UserId::generate()->toString()
        };
        $action = new ListUserSessionsAction($queries, new OwnedSessionsResponder($this->responses));
        $this->expectException($exception::class);
        $action->handle($this->request('userId', $value));
    }

    /**
     * Supplies separate administrative admission and authorization outcomes
     *
     * @return iterable<array{string, Throwable}>
     */
    public static function administrativeRefusals(): iterable
    {
        yield ['invalid', new ValidationException(['user_id' => ['Invalid value.']])];
        yield ['self', new PermissionDenied()];
        yield ['denied', new PermissionDenied()];
    }

    /**
     * Dispatches revocation only for the admitted identity and presents safe success
     */
    public function testOwnedRevocation(): void
    {
        $target = RefreshSessionId::generate();
        $sessions = $this->createMock(RefreshSessionRepository::class);
        $sessions->expects(self::once())->method('getById')->with($target)
            ->willReturn($this->session($target, $this->actor->getId()));
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::once())->method('execute')->with(self::callback(
            fn(RevokeSession $command): bool => $command->getActorId()->equals($this->actor->getId())
                && $command->getCurrentSessionId()->equals($this->current->getId())
                && $command->getTargetSessionId()->equals($target) && $command->getReason() === null
        ));
        $action = new RevokeOwnedSessionAction($sessions, $commands, new SessionRevocationResponder($this->responses));
        $response = $action->handle($this->request('sessionId', $target->toString()));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(['status' => 'success', 'data' => null], json_decode((string) $response->getBody(), true));
    }

    /**
     * Rejects inadmissible targets before any revocation command can run
     */
    #[DataProvider('ownedRefusals')]
    public function testOwnedRevocationRefusal(string $case, Throwable $exception): void
    {
        $target = RefreshSessionId::generate();
        $sessions = $this->createMock(RefreshSessionRepository::class);
        if ($case === 'unauthenticated' || $case === 'invalid') {
            $sessions->expects(self::never())->method('getById');
        } else {
            $record = match ($case) {
                'foreign' => $this->session($target, UserId::generate()),
                'aliased' => $this->session(RefreshSessionId::generate(), $this->actor->getId()),
                default   => null
            };
            $sessions->expects(self::once())->method('getById')->with($target)->willReturn($record);
        }
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('execute');
        $request = $this->request('sessionId', $case === 'invalid' ? 'invalid' : $target->toString());
        $action = new RevokeOwnedSessionAction($sessions, $commands, new SessionRevocationResponder($this->responses));
        if ($case === 'unauthenticated') {
            $request = $request->withoutAttribute(CurrentPrincipalProvider::class);
        }
        $this->expectException($exception::class);
        $action->handle($request);
    }

    /**
     * Supplies failures that must not reach revocation dispatch
     *
     * @return iterable<array{string, Throwable}>
     */
    public static function ownedRefusals(): iterable
    {
        yield ['unauthenticated', new AuthenticationRequired()];
        yield ['invalid', new ValidationException(['session_id' => ['Invalid value.']])];
        yield ['missing', new StateConflict()];
        yield ['foreign', new StateConflict()];
        yield ['aliased', new StateConflict()];
    }

    /**
     * Dispatches the exact authorized actor, current session, target session and bounded reason
     */
    public function testAdministrativeRevocationDispatchesAdmittedTarget(): void
    {
        $owner = UserId::generate();
        $target = RefreshSessionId::generate();
        $reason = 'Device retired';
        $sessions = $this->createMock(RefreshSessionRepository::class);
        $sessions->expects(self::once())->method('getById')->with($target)
            ->willReturn($this->session($target, $owner));
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::once())->method('execute')->with(self::callback(
            fn(RevokeSession $command): bool => $command->getActorId()->equals($this->actor->getId())
                && $command->getCurrentSessionId()->equals($this->current->getId())
                && $command->getTargetSessionId()->equals($target) && $command->getReason()?->toString() === $reason
        ));
        $action = new RevokeUserSessionAction($sessions, $commands, new SessionRevocationResponder($this->responses));

        $response = $action->handle($this->revocationRequest($owner->toString(), $target->toString(), $reason));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(['status' => 'success', 'data' => null], json_decode((string) $response->getBody(), true));
    }

    /**
     * Rejects invalid intent or ownership without issuing a revocation command
     */
    #[DataProvider('adminRevocationRefusals')]
    public function testAdministrativeRevocationRefusal(string $case, Throwable $expected): void
    {
        $owner = $case === 'self' ? $this->actor->getId() : UserId::generate();
        $target = RefreshSessionId::generate();
        $reason = match ($case) {
            'empty reason'   => '',
            'private reason' => 'Password is private',
            default          => 'Device retired',
        };
        $request = $this->revocationRequest(
            $case === 'bad user' ? 'invalid' : $owner->toString(),
            $case === 'bad session' ? 'invalid' : $target->toString(),
            $reason
        );
        if ($case === 'unauthenticated') {
            $request = $request->withoutAttribute(CurrentPrincipalProvider::class);
        }
        $sessions = $this->createMock(RefreshSessionRepository::class);
        if ($case === 'unknown' || $case === 'wrong owner') {
            $sessions->expects(self::once())->method('getById')->with($target)->willReturn(
                $case === 'unknown' ? null : $this->session($target, UserId::generate())
            );
        } else {
            $sessions->expects(self::never())->method('getById');
        }
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('execute');
        $action = new RevokeUserSessionAction($sessions, $commands, new SessionRevocationResponder($this->responses));
        $this->expectException($expected::class);

        $action->handle($request);
    }

    /**
     * Lists the admission failures that must not dispatch a command
     *
     * @return iterable<string, array{string, Throwable}>
     */
    public static function adminRevocationRefusals(): iterable
    {
        yield 'no trusted actor' => ['unauthenticated', new AuthenticationRequired()];
        yield 'invalid user ID' => ['bad user', new ValidationException(['user_id' => ['Invalid value.']])];
        yield 'invalid session ID' => ['bad session', new ValidationException(['session_id' => ['Invalid value.']])];
        yield 'no reason' => ['empty reason', new ValidationException(['reason' => ['Required.']])];
        yield 'sensitive reason' => ['private reason', new ValidationException(['reason' => ['Unsafe reason.']])];
        yield 'self target' => ['self', new PermissionDenied()];
        yield 'unknown session' => ['unknown', new StateConflict()];
        yield 'foreign session' => ['wrong owner', new StateConflict()];
    }

    /**
     * Supplies both independently checked route identities and a source-validated reason
     */
    private function revocationRequest(string $userId, string $sessionId, string $reason): ServerRequestInterface
    {
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturnMap([['userId', null, $userId], ['sessionId', null, $sessionId]]);

        return $this->request('userId', $userId)->withMethod('DELETE')->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(JsonBody::class, ['reason' => $reason]);
    }

    /**
     * Creates a validated request with optional matched route identity
     */
    private function request(?string $argument = null, string $value = ''): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/me/sessions')
            ->withAttribute(CurrentPrincipalProvider::class, $this->provider)
            ->withAttribute(QueryString::class, []);
        if ($argument === null) {
            return $request;
        }
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturnMap([[$argument, null, $value]]);

        return $request->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
    }

    /**
     * Creates a live session without accessing persistence
     */
    private function session(RefreshSessionId $id, UserId $owner): RefreshSession
    {
        $now = $this->clock->now();

        return RefreshSession::start(
            $id,
            $owner,
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            $this->actor->getAuthenticationVersion(),
            false
        );
    }
}
