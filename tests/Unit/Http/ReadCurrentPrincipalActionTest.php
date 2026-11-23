<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Me\ReadCurrentPrincipalAction;
use App\Adapter\Http\Api\Auth\RequestAuthenticationContext;
use App\Adapter\Http\Responder\Api\V1\Me\ReadCurrentPrincipalResponder;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
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
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
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
     * Returns sorted current authority with the confirmed safe identity only
     */
    public function testCurrentPrincipal(): void
    {
        $clock = new AccessClock();
        $now = $clock->now();
        $user = User::invite(UserId::generate(), EmailAddress::fromString('safe@example.test'), $now);
        $user->activate(PasswordHash::fromString((string) password_hash('private-password', PASSWORD_BCRYPT)), $now);
        $write = Permission::define(PermissionId::generate(), PermissionName::fromString('WRITE_NOTES'), $now);
        $read = Permission::define(PermissionId::generate(), PermissionName::fromString('READ_NOTES'), $now);
        $writer = Role::define(RoleId::generate(), RoleName::fromString('ROLE_WRITER'), [$write->getId()], $now);
        $reader = Role::define(RoleId::generate(), RoleName::fromString('ROLE_READER'), [$read->getId()], $now);
        $user->assignRole($writer->getId(), $now);
        $user->assignRole($reader->getId(), $now);
        $provider = $this->provider($user, $clock, [$writer, $reader], [$write, $read]);
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::once())->method('fetch')->willReturn(UserView::fromUser($user));

        $response = $this->action($queries)->handle(new ServerRequestFactory()->createServerRequest('GET', '/api/v1/me')
            ->withAttribute(CurrentPrincipalProvider::class, $provider));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame([
            'status' => 'success',
            'data'   => [
                'user_id'     => $user->getId()->toString(),
                'email'       => 'safe@example.test',
                'roles'       => ['ROLE_READER', 'ROLE_WRITER'],
                'permissions' => ['READ_NOTES', 'WRITE_NOTES']
            ]
        ], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
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
        $provider = $this->provider($user, $clock, [], []);
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
     * Resolves authority through package contracts against in-memory fixtures
     *
     * @param User                       $user
     * @param AccessClock                $clock
     * @param array<int, Role>            $roleDefinitions
     * @param array<int, Permission>      $permissionDefinitions
     */
    private function provider(
        User $user,
        AccessClock $clock,
        array $roleDefinitions,
        array $permissionDefinitions
    ): CurrentPrincipalProvider {
        $now = $clock->now();
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
        $roles->method('getByIds')->willReturn(array_values($roleDefinitions));
        $permissions = $this->createStub(PermissionRepository::class);
        $permissions->method('getByIds')->willReturn(array_values($permissionDefinitions));

        return new CurrentPrincipalProvider(new RequestAuthenticationContext(new AuthenticationContext(
            $user->getId(),
            $session->getId(),
            $user->getAuthenticationVersion()
        )), new AuthoritativePrincipalResolver($users, $sessions, $roles, $permissions, $clock));
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
