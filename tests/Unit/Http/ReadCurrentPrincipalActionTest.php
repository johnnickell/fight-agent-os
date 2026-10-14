<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Me\ReadCurrentPrincipalAction;
use App\Adapter\Http\Api\Auth\RequestAuthenticationContext;
use App\Adapter\Http\Responder\Api\V1\Me\ReadCurrentPrincipalResponder;
use App\Application\Failure\AuthenticationRequired;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessClock;

/**
 * Rejects missing request authority and unusable safe identity query results
 */
final class ReadCurrentPrincipalActionTest extends TestCase
{
    /**
     * Refuses dispatch when the required authenticated provider is absent
     */
    public function testMissingProvider(): void
    {
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::never())->method('fetch');
        $this->expectException(AuthenticationRequired::class);
        $this->action($queries)->handle(new ServerRequestFactory()->createServerRequest('GET', '/api/v1/me'));
    }

    /**
     * Refuses missing, mismatched or no-longer-active safe identity results
     */
    #[DataProvider('invalidResults')]
    public function testInvalidIdentity(string $case): void
    {
        $clock = new AccessClock();
        $now = $clock->now();
        $user = User::invite(UserId::generate(), EmailAddress::fromString('safe@example.test'), $now);
        $user->activate(PasswordHash::fromString((string) password_hash('private-password', PASSWORD_BCRYPT)), $now);
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            $user->getAuthenticationVersion(),
            false
        );
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturn($session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturn([]);
        $provider = new CurrentPrincipalProvider(new RequestAuthenticationContext(new AuthenticationContext(
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
        // Resolve first; the result under test can independently change after the early gate.
        $provider->getCurrentPrincipal();
        $result = match ($case) {
            'wrong type' => new \stdClass(),
            'missing' => null,
            'wrong identity' => UserView::fromUser(User::invite(
                UserId::generate(),
                EmailAddress::fromString('other@example.test'), $now
            )),
            default => null
        };
        if ($case === 'inactive') {
            $user->disable($now);
            $result = UserView::fromUser($user);
        }
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::once())->method('fetch')->willReturn($result);
        $this->expectException(AuthenticationRequired::class);
        $this->action($queries)->handle(new ServerRequestFactory()->createServerRequest('GET', '/api/v1/me')
            ->withAttribute(CurrentPrincipalProvider::class, $provider));
    }

    /**
     * Supplies invalid package-query result contracts
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidResults(): iterable
    {
        foreach (['wrong type', 'missing', 'wrong identity', 'inactive'] as $case) {
            yield $case => [$case];
        }
    }

    /**
     * Creates the real Action without registering a fixture route
     */
    private function action(QueryBus $queries): ReadCurrentPrincipalAction
    {
        return new ReadCurrentPrincipalAction($queries, new ReadCurrentPrincipalResponder(
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory())
        ));
    }
}
