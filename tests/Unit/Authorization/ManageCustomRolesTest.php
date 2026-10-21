<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\ManageCustomRoles;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\PermissionDenied;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\Command\CreateCustomRole;
use Fight\AccessControl\Domain\AccessControl\Role\Command\RemoveCustomRole;
use Fight\AccessControl\Domain\AccessControl\Role\Command\RenameCustomRole;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Class ManageCustomRolesTest
 */
final class ManageCustomRolesTest extends TestCase
{
    private AuthenticationContext $authentication;
    private User $actor;
    private Role $target;
    private mixed $result;
    private string $fault = '';
    /** @var list<CreateCustomRole|RenameCustomRole|RemoveCustomRole> */
    private array $commands = [];

    /**
     * Creates only the exact selected permission on authoritative managed state
     */
    private function service(string $permission): ManageCustomRoles
    {
        $now = new DateTimeImmutable('2026-10-20T12:00:00Z');
        $definition = Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString($permission),
            PermissionTier::SUPER_ADMIN_ONLY,
            $now
        );
        $admin = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            [$definition->getId()],
            $now
        );
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('operator@example.test'), $now);
        $this->actor->activate(
            PasswordHash::fromString((string) password_hash('fixture', PASSWORD_BCRYPT, ['cost' => 4])),
            $now
        );
        $this->actor->assignRole($admin->getId(), $now);
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $this->actor->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 hour'),
            $now->modify('+1 day'),
            1,
            false
        );
        $this->authentication = new AuthenticationContext($this->actor->getId(), $session->getId(), 1);
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($this->actor);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturnCallback(fn() => $this->fault === 'session' ? null : $session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturn([$admin]);
        $roles->method('getByName')->willReturn($admin);
        $permissions = $this->createStub(PermissionRepository::class);
        $permissions->method('getByIds')->willReturn([$definition]);
        $permissions->method('getByName')->willReturnCallback(
            fn() => $this->fault === 'installation' ? null : $definition
        );
        $commands = $this->createStub(CommandBus::class);
        $commands->method('execute')->willReturnCallback(function ($command): void {
            $this->commands[] = $command;
        });
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(fn() => $this->result);
        $this->target = Role::define(RoleId::generate(), RoleName::fromString('ROLE_CUSTOM'), [], $now);
        $this->result = RoleView::fromRole($this->target);

        return new ManageCustomRoles(
            new AuthenticatedPermissions(
                new AuthoritativePrincipalResolver($users, $sessions, $roles, $permissions, new AccessClock($now)),
                new PermissionGuard()
            ),
            new InstallationAuthority($users, $roles, $permissions),
            $commands,
            $queries
        );
    }

    /**
     * Authorizes disjoint permissions without READ_ROLES and derives actor provenance
     */
    #[DataProvider('operations')]
    public function testSuccess(string $operation, string $permission, string $commandType): void
    {
        $service = $this->service($permission);
        $result = $this->invoke($service, $operation);
        self::assertCount(1, $this->commands);
        $command = $this->commands[0];
        self::assertSame($commandType, $command::class);
        self::assertEquals($this->actor->getId(), $command->getActorId());
        self::assertEquals($this->target->getId(), $command->getRoleId());
        if ($command instanceof RenameCustomRole || $command instanceof RemoveCustomRole) {
            self::assertTrue($this->target->getRevision()->equals($command->getExpectedRevision()));
        }
        self::assertSame($operation === 'remove' ? null : $this->result, $result);
    }

    /**
     * Rejects stale authentication and unavailable installation policy before dispatch
     */
    #[DataProvider('operations')]
    public function testDenials(string $operation, string $permission): void
    {
        $service = $this->service($permission);
        foreach (['session', 'installation'] as $fault) {
            $this->fault = $fault;
            try {
                $this->invoke($service, $operation);
                self::fail('Invalid authority dispatched.');
            } catch (PermissionDenied) {
                self::assertSame([], $this->commands);
            }
        }
    }

    /**
     * Rejects another operation permission for every non-HTTP caller
     */
    #[DataProvider('operations')]
    public function testExactPermission(string $operation, string $permission): void
    {
        $service = $this->service($permission === 'CREATE_ROLES' ? 'UPDATE_ROLES' : 'CREATE_ROLES');
        $this->expectException(PermissionDenied::class);
        $this->invoke($service, $operation);
    }

    /**
     * Requires explicit confirmation in Application independently of HTTP validation
     */
    public function testConfirmation(): void
    {
        $service = $this->service('DELETE_ROLES');
        try {
            $service->remove($this->authentication, $this->target->getId(), $this->target->getRevision(), false);
            self::fail('Unconfirmed removal dispatched.');
        } catch (InvalidArgumentException) {
            self::assertSame([], $this->commands);
        }
    }

    /**
     * Does not invent a successful current View after lost or inconsistent post-commit reads
     */
    public function testUncertainRead(): void
    {
        $service = $this->service('CREATE_ROLES');
        $other = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_OTHER'),
            [],
            new DateTimeImmutable('2026-10-20T12:00:00Z')
        );
        foreach ([null, 'unavailable', RoleView::fromRole($other)] as $result) {
            $this->result = $result;
            try {
                $this->invoke($service, 'create');
                self::fail('Unavailable current state became success.');
            } catch (LogicException) {
                self::assertNotEmpty($this->commands);
            }
        }
    }

    /**
     * Supplies the exact operation-to-permission mapping
     *
     * @return iterable<array{string, string, class-string}>
     */
    public static function operations(): iterable
    {
        yield ['create', 'CREATE_ROLES', CreateCustomRole::class];
        yield ['rename', 'UPDATE_ROLES', RenameCustomRole::class];
        yield ['remove', 'DELETE_ROLES', RemoveCustomRole::class];
    }

    /**
     * Invokes typed caller intent without exposing an actor parameter
     */
    private function invoke(ManageCustomRoles $service, string $operation): ?RoleView
    {
        if ($operation === 'create') {
            return $service->create($this->authentication, $this->target->getId(), $this->target->getName());
        }
        if ($operation === 'rename') {
            return $service->rename(
                $this->authentication,
                $this->target->getId(),
                $this->target->getName(),
                $this->target->getRevision()
            );
        }
        $service->remove($this->authentication, $this->target->getId(), $this->target->getRevision(), true);

        return null;
    }
}
