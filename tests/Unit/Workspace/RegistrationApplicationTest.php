<?php

declare(strict_types=1);

namespace Tests\Unit\Workspace;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\PermissionDenied;
use App\Application\Workspace\BootstrapWorkspace;
use App\Application\Workspace\GetRepositoryRegistration;
use App\Application\Workspace\ListRepositoryRegistrations;
use App\Application\Workspace\RegisterRepository;
use App\Application\Workspace\RegistrationAccess;
use App\Application\Workspace\RegistrationFences;
use App\Application\Workspace\RegistrationStore;
use App\Application\Workspace\RegistrationView;
use App\Application\Workspace\WorkspaceView;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Workspace\RegistrationConflict;
use App\Domain\Workspace\RegistrationInputRejected;
use App\Domain\Workspace\RegistrationIntent;
use App\Domain\Workspace\RegistrationMetadata;
use App\Domain\Workspace\RegistrationSources;
use App\Domain\Workspace\RepositoryId;
use App\Domain\Workspace\RepositoryRegistered;
use App\Domain\Workspace\RepositoryRegistration;
use App\Domain\Workspace\WorkspaceId;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
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
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects owned orchestration independently of HTTP and database mechanics
 */
final class RegistrationApplicationTest extends TestCase
{
    private RegistrationStore&MockObject $store;
    private RegistrationFences&MockObject $fences;
    private TransactionalUnitOfWork $transactions;
    private RegistrationAccess $access;
    private AuthenticationContext $authentication;
    private Clock $clock;
    private WorkspaceView $workspace;
    private User $actor;
    private bool $insideTransaction = false;

    /**
     * Supplies real package authority values and deterministic time through injected capabilities
     */
    protected function setUp(): void
    {
        $now = new DateTimeImmutable('2026-10-20T12:00:00.123456+00:00');
        $this->clock = $this->createStub(Clock::class);
        $this->clock->method('now')->willReturn($now);
        $this->workspace = new WorkspaceView(WorkspaceId::generate(), $now);
        $this->store = $this->createMock(RegistrationStore::class);
        $this->store->method('initialWorkspace')->willReturn($this->workspace);
        $this->fences = $this->createMock(RegistrationFences::class);
        $this->transactions = $this->createStub(TransactionalUnitOfWork::class);
        $this->transactions->method('commitTransactional')->willReturnCallback(function (callable $operation): mixed {
            $this->insideTransaction = true;
            try {
                return $operation();
            } finally {
                $this->insideTransaction = false;
            }
        });
        $permissions = [];
        foreach ([RegistrationAccess::VIEW, RegistrationAccess::REGISTER] as $name) {
            $permissions[] = Permission::defineManaged(
                PermissionId::generate(),
                PermissionName::fromString($name),
                PermissionTier::ADMIN_SAFE,
                $now
            );
        }
        $role = Role::defineManaged(
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            array_map(static fn(Permission $p): PermissionId => $p->getId(), $permissions),
            $now
        );
        $this->actor = User::invite(
            UserId::generate(),
            EmailAddress::fromString('actor@example.test'),
            $now->modify('-1 day')
        );
        $this->actor->activate(
            PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_BCRYPT)),
            $now->modify('-1 day')
        );
        $this->actor->assignRole($role->getId(), $now->modify('-1 hour'));
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $this->actor->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now->modify('-1 minute'),
            $now->modify('+1 hour'),
            $now->modify('+1 day'),
            $this->actor->getAuthenticationVersion(),
            false
        );
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($this->actor);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByName')->willReturn($role);
        $roles->method('getById')->willReturn($role);
        $roles->method('getByIds')->willReturn([$role]);
        $catalog = $this->createStub(PermissionRepository::class);
        $catalog->method('getByIds')->willReturn($permissions);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturn($session);
        $authority = new AuthenticatedPermissions(
            new AuthoritativePrincipalResolver($users, $sessions, $roles, $catalog, $this->clock),
            new PermissionGuard()
        );
        $this->access = new RegistrationAccess($authority, $this->store, $users, $roles);
        $this->authentication = new AuthenticationContext(
            $this->actor->getId(),
            $session->getId(),
            $this->actor->getAuthenticationVersion()
        );
    }

    /**
     * Registers internally generated identity and dispatches only after the owned transaction completes
     */
    public function testOwnedRegistrationAndPostCommitFact(): void
    {
        $intent = new RegistrationIntent(
            new RegistrationMetadata('Project', 'project'),
            new RegistrationSources(),
            'intent-0001'
        );
        $this->fences->expects(self::once())->method('holdAuthority')->with($this->authentication);
        $this->store->method('conflicts')->willReturn([]);
        $registered = null;
        $this->store->expects(self::once())->method('add')->willReturnCallback(function (
            RepositoryRegistration $registration
        ) use (&$registered): RegistrationView {
            self::assertTrue($this->insideTransaction);
            $registered = $registration;

            return RegistrationView::fromRegistration($registration);
        });
        $events = $this->createMock(EventDispatcher::class);
        $events->expects(self::once())->method('trigger')->willReturnCallback(function (
            RepositoryRegistered $event
        ) use (&$registered): void {
            self::assertFalse($this->insideTransaction);
            self::assertTrue($registered->id->equals($event->repositoryId));
        });
        $operation = new RegisterRepository(
            $this->store,
            $this->fences,
            $this->access,
            $this->transactions,
            $this->clock,
            $events
        );
        $view = $operation->register($this->authentication, $this->workspace->id, $intent);
        self::assertSame('project', $view->data['slug']);
        self::assertSame($this->clock->now()->format('Y-m-d\TH:i:s.uP'), $view->data['created_at']);
    }

    /**
     * Refuses advisory conflicts before minting or dispatching a new registration
     */
    public function testPreflightConflictHasNoRegistrationEffect(): void
    {
        $this->store->method('conflicts')->willReturn(['source']);
        $this->store->expects(self::never())->method('add');
        $events = $this->createMock(EventDispatcher::class);
        $events->expects(self::never())->method('trigger');
        $this->expectException(RegistrationConflict::class);
        $operation = new RegisterRepository(
            $this->store,
            $this->fences,
            $this->access,
            $this->transactions,
            $this->clock,
            $events
        );
        $operation->register($this->authentication, $this->workspace->id, new RegistrationIntent(
            new RegistrationMetadata('Project', 'project'),
            new RegistrationSources(),
            'intent-0001'
        ));
    }

    /**
     * Enforces bounded Application pagination even when no HTTP validator runs
     */
    #[DataProvider('invalidPages')]
    public function testDirectPaginationValidation(int $offset, int $limit): void
    {
        $this->store->expects(self::never())->method('page');
        $this->expectException(RegistrationInputRejected::class);
        (new ListRepositoryRegistrations($this->store, $this->fences, $this->access, $this->transactions))->list(
            $this->authentication,
            $this->workspace->id,
            $offset,
            $limit
        );
    }

    /**
     * Supplies out-of-bound direct-call pagination values
     *
     * @return iterable<array{int, int}>
     */
    public static function invalidPages(): iterable
    {
        yield [-1, 25];
        yield [100001, 25];
        yield [0, 0];
        yield [0, 101];
    }

    /**
     * Rejects stale authentication on the shared non-HTTP read boundary
     */
    public function testDirectReadRechecksAuthentication(): void
    {
        $stale = new AuthenticationContext(
            $this->actor->getId(),
            $this->authentication->getRefreshSessionId(),
            $this->actor->getAuthenticationVersion() + 1
        );
        $this->store->expects(self::never())->method('get');
        $this->expectException(PermissionDenied::class);
        (new GetRepositoryRegistration($this->store, $this->fences, $this->access, $this->transactions))->get(
            $stale,
            $this->workspace->id,
            RepositoryId::generate()
        );
    }
}
