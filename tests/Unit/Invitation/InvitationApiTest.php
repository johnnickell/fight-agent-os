<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\IncompleteInvitation;
use App\Application\Failure\PermissionDenied;
use App\Application\Failure\ResourceNotFound;
use App\Application\Failure\StateConflict;
use App\Application\Invitation\ConsoleAssignmentPolicy;
use App\Application\Invitation\InvitationRateLimiter;
use App\Application\Invitation\IssueInvitation;
use App\Application\Invitation\ReadInvitationStatus;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\InvitationMode;
use App\Domain\Invitation\InvitationOperation;
use App\Domain\Invitation\InvitationRatePolicy;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\FindInvitationDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPermissionPlanItem;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyChangeAction;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyPlan;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedRolePlanItem;
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
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Exception\DuplicateEmailException;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\AccessClock;

/**
 * Protects exact authenticated invitation policy, independent-step evidence and safe status use cases
 */
final class InvitationApiTest extends TestCase
{
    private AuthenticationContext $authentication;
    private User $actor;
    private ?UserView $target = null;
    /** @var list<object> */
    private array $effects = [];
    /** @var list<AuditEvidence> */
    private array $audits = [];
    /** @var list<InvitationOperation> */
    private array $admissions = [];
    private ReadInvitationStatus $reader;
    private string $fault = '';

    /**
     * Enforces each exact permission combination independently of role name and HTTP metadata
     */
    #[DataProvider('permissions')]
    public function testPermissionMatrix(int $mask, InvitationMode $mode): void
    {
        $names = [];
        foreach (['INVITE_USERS', 'ASSIGN_SUPER_ADMIN', 'MANAGE_USER_INVITATIONS'] as $bit => $name) {
            if (($mask & (1 << $bit)) !== 0) {
                $names[] = $name;
            }
        }
        $issuer = $this->issuer($names);
        $allowed = ($mask & 1) !== 0 && ($mode === InvitationMode::ORDINARY || ($mask & 2) !== 0);
        try {
            $outcome = $issuer->issue($this->authentication, EmailAddress::fromString('NEW@EXAMPLE.test'), $mode, true);
            self::assertTrue($allowed);
            self::assertTrue($outcome->auditConfirmed);
            self::assertSame('confirmed', $outcome->assignment);
            self::assertSame($mode->roleId()->toString(), $this->audits[0]->context()['role_id']);
            self::assertSame($this->actor->getId()->toString(), $this->audits[0]->actorId());
            self::assertSame($outcome->userId, $this->audits[0]->subjectId());
            self::assertInstanceOf(InvitePendingUser::class, $this->effects[0]);
            self::assertSame('new@example.test', $this->effects[0]->getEmail()->toString());
            self::assertInstanceOf(AssignRoleToUser::class, $this->effects[1]);
            self::assertEquals($mode->roleId(), $this->effects[1]->getRoleId());
        } catch (PermissionDenied) {
            self::assertFalse($allowed);
            self::assertSame([], $this->effects);
            self::assertSame([], $this->admissions);
        }
    }

    /**
     * Supplies all permission subsets for both managed modes
     *
     * @return iterable<array{int, InvitationMode}>
     */
    public static function permissions(): iterable
    {
        foreach (range(0, 7) as $mask) {
            foreach (InvitationMode::cases() as $mode) {
                yield [$mask, $mode];
            }
        }
    }

    /**
     * Rejects absent confirmation before admission or mutation
     */
    public function testConfirmation(): void
    {
        $issuer = $this->issuer();
        try {
            $issuer->issue(
                $this->authentication,
                EmailAddress::fromString('new@example.test'),
                InvitationMode::SUPER_ADMIN,
                false
            );
            self::fail('Missing confirmation was accepted.');
        } catch (ValidationException) {
            self::assertSame([], $this->admissions);
            self::assertSame([], $this->effects);
        }
    }

    /**
     * Requires fresh authority rather than an earlier successful permission projection
     */
    public function testStaleAuthentication(): void
    {
        $issuer = $this->issuer();
        $this->actor->disable(new DateTimeImmutable());
        $this->expectException(PermissionDenied::class);
        $issuer->issue(
            $this->authentication,
            EmailAddress::fromString('new@example.test'),
            InvitationMode::ORDINARY,
            false
        );
    }

    /**
     * Preserves committed-step uncertainty without retries or arbitrary private exception output
     */
    #[DataProvider('failures')]
    public function testRetainedOutcomes(string $fault, string $assignment, bool $known): void
    {
        $this->fault = $fault;
        $issuer = $this->issuer();
        try {
            $issuer->issue(
                $this->authentication,
                EmailAddress::fromString('new@example.test'),
                InvitationMode::ORDINARY,
                false
            );
            self::fail('Incomplete issuance was reported as complete.');
        } catch (IncompleteInvitation $failure) {
            self::assertSame($assignment, $failure->outcome->assignment);
            self::assertSame($known, $failure->outcome->userId !== null);
            self::assertStringNotContainsString('private-marker', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            self::assertLessThanOrEqual(2, count($this->effects));
        }
    }

    /**
     * Supplies precommit, postcommit and inspection uncertainty boundaries
     *
     * @return iterable<string, array{string, string, bool}>
     */
    public static function failures(): iterable
    {
        foreach (
            [
            ['origin', 'unknown', false], ['origin-postcommit', 'unconfirmed', true],
            ['assignment', 'unconfirmed', true], ['assignment-postcommit', 'confirmed', true],
            ['audit', 'confirmed', true], ['query', 'unknown', false], ['wrong-user', 'unknown', false],
            ['wrong-email', 'unknown', false], ['wrong-state', 'unknown', false],
            ['delivery-null', 'confirmed', true], ['delivery-wrong', 'confirmed', true],
            ['delivery-query', 'confirmed', true],
            ['veto-assignment', 'unconfirmed', true], ['revoke', 'unconfirmed', true]
            ] as $case
        ) {
            yield $case[0] => $case;
        }
    }

    /**
     * Maps only declared conflict boundaries before disclosing any target
     */
    #[DataProvider('conflicts')]
    public function testConflicts(string $fault): void
    {
        $this->fault = $fault;
        $issuer = $this->issuer();
        $this->expectException(StateConflict::class);
        $issuer->issue(
            $this->authentication,
            EmailAddress::fromString('new@example.test'),
            InvitationMode::ORDINARY,
            false
        );
    }

    /**
     * Supplies policy drift, existing canonical address and concurrent duplicate failures
     *
     * @return iterable<array{string}>
     */
    public static function conflicts(): iterable
    {
        foreach (['policy', 'duplicate', 'race'] as $fault) {
            yield [$fault];
        }
    }

    /**
     * Requires exact management authority before status admission and returns the package View directly
     */
    public function testStatus(): void
    {
        $this->issuer(['MANAGE_USER_INVITATIONS']);
        $target = UserId::generate();
        $view = $this->reader->read($this->authentication, $target);
        self::assertEquals($target, $view->getUserId());
        self::assertSame(CredentialDeliveryStatus::PENDING, $view->getStatus());
        self::assertSame([InvitationOperation::STATUS], $this->admissions);
        self::assertSame([], $this->effects);
    }

    /**
     * Refuses status reads even when invitation authority exists
     */
    public function testStatusForbidden(): void
    {
        $this->issuer(['INVITE_USERS', 'ASSIGN_SUPER_ADMIN']);
        $this->expectException(PermissionDenied::class);
        $this->reader->read($this->authentication, UserId::generate());
    }

    /**
     * Separates a missing generation from a mismatched or unavailable dependency result
     *
     * @param string $fault
     * @param string $exception
     *
     * @phpstan-param class-string<\Throwable> $exception
     */
    #[DataProvider('statusFailures')]
    public function testStatusFailures(string $fault, string $exception): void
    {
        $this->fault = $fault;
        $this->issuer();
        $this->expectException($exception);
        $this->reader->read($this->authentication, UserId::generate());
    }

    /**
     * Supplies explicit safe query failure meanings
     *
     * @return iterable<array{string, class-string<\Throwable>}>
     */
    public static function statusFailures(): iterable
    {
        yield ['delivery-null', ResourceNotFound::class];
        yield ['delivery-wrong', LogicException::class];
    }

    /**
     * Rejects invalid production budgets rather than silently disabling admission
     */
    #[DataProvider('invalidPolicies')]
    public function testInvalidPolicy(int $window, int $issue, int $status, int $capacity): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InvitationRatePolicy($window, $issue, $status, $capacity);
    }

    /**
     * Supplies every budget boundary
     *
     * @return iterable<array{int, int, int, int}>
     */
    public static function invalidPolicies(): iterable
    {
        foreach (
            [[0, 1, 1, 1], [86401, 1, 1, 1], [1, 0, 1, 1], [1, 1001, 1, 1],
            [1, 1, 0, 1], [1, 1, 10001, 1], [1, 1, 1, 0], [1, 1, 1, 100001]] as $values
        ) {
            yield $values;
        }
    }

    /**
     * Creates real package principal resolution with controlled repository state and observable bus effects
     *
     * @param array $names
     *
     * @phpstan-param list<string> $names
     */
    private function issuer(
        array $names = ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN', 'MANAGE_USER_INVITATIONS']
    ): IssueInvitation {
        $now = new DateTimeImmutable('2026-10-17T12:00:00Z');
        $permissions = array_map(static fn(string $name): Permission => Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString($name),
            PermissionTier::SUPER_ADMIN_ONLY,
            $now
        ), $names);
        $role = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            array_map(static fn($p) => $p->getId(), $permissions),
            $now
        );
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('actor@example.test'), $now);
        $this->actor->activate(
            PasswordHash::fromString((string) password_hash('unit-password', PASSWORD_BCRYPT)),
            $now
        );
        $this->actor->assignRole($role->getId(), $now);
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $this->actor->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            $this->actor->getAuthenticationVersion(),
            false
        );
        $this->authentication = new AuthenticationContext(
            $this->actor->getId(),
            $session->getId(),
            $this->actor->getAuthenticationVersion()
        );
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($this->actor);
        $users->method('getByEmail')->willReturn($this->fault === 'duplicate' ? $this->actor : null);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturn($session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByName')->willReturn($role);
        $roles->method('getByIds')->willReturn([$role]);
        $permissionStore = $this->createStub(PermissionRepository::class);
        $permissionStore->method('getByIds')->willReturn($permissions);
        $authority = new AuthenticatedPermissions(new AuthoritativePrincipalResolver(
            $users,
            $sessions,
            $roles,
            $permissionStore,
            new AccessClock($now)
        ), new PermissionGuard());
        $limiter = $this->createStub(InvitationRateLimiter::class);
        $limiter->method('consume')->willReturnCallback(function (UserId $actor, InvitationOperation $operation): void {
            $this->admissions[] = $operation;
        });
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(function ($query) use ($now) {
            if ($query instanceof GetUserById) {
                if ($this->fault === 'query') {
                    throw new RuntimeException('private-marker');
                }

                return $this->target;
            }
            if ($query instanceof FindInvitationDeliveryStatus) {
                if ($this->fault === 'delivery-query') {
                    throw new RuntimeException('private-marker');
                }

                return $this->fault === 'delivery-null' ? null : new InvitationDeliveryStatusView(
                    $this->fault === 'delivery-wrong' ? UserId::generate() : $query->getUserId(),
                    CredentialDeliveryStatus::PENDING,
                    $now->modify('+7 days')
                );
            }
            if ($this->fault === 'policy') {
                throw new LogicException('private-marker');
            }
            $policy = InstallationManagedPolicy::create();

            return new ManagedPolicyPlan(
                array_map(
                    static fn($d) => new ManagedPermissionPlanItem($d, ManagedPolicyChangeAction::UNCHANGED),
                    $policy->getPermissions()
                ),
                array_map(
                    static fn($d) => new ManagedRolePlanItem($d, ManagedPolicyChangeAction::UNCHANGED),
                    $policy->getRoles()
                )
            );
        });
        $commands = $this->createStub(CommandBus::class);
        $commands->method('execute')->willReturnCallback(function ($command) use ($now): void {
            if ($this->fault === 'race') {
                throw new DuplicateEmailException('private-marker');
            }
            if ($this->fault === 'origin' || ($this->fault === 'assignment' && $command instanceof AssignRoleToUser)) {
                throw new RuntimeException('private-marker');
            }
            if ($this->fault === 'veto-assignment' && $command instanceof AssignRoleToUser) {
                return;
            }
            $this->effects[] = $command;
            $id = $command instanceof InvitePendingUser ? $command->getUserId() : $command->getTargetUserId();
            $this->target = new UserView(
                $this->fault === 'wrong-user' ? UserId::generate() : $id,
                EmailAddress::fromString($this->fault === 'wrong-email' ? 'wrong@example.test' : 'new@example.test'),
                $this->fault === 'wrong-state' ? UserState::DISABLED : UserState::PENDING_ACTIVATION,
                $command instanceof AssignRoleToUser ? [$command->getRoleId()] : [],
                $now,
                $now
            );
            if ($this->fault === 'revoke') {
                $this->actor->disable($now);
            }
            if (
                ($this->fault === 'origin-postcommit' && $command instanceof InvitePendingUser)
                || ($this->fault === 'assignment-postcommit' && $command instanceof AssignRoleToUser)
            ) {
                throw new RuntimeException('private-marker');
            }
        });
        $transactions = $this->createStub(TransactionalUnitOfWork::class);
        $transactions->method('commitTransactional')->willReturnCallback(static fn(callable $work) => $work());
        $audit = $this->createStub(AuditEvidenceRepository::class);
        $audit->method('add')->willReturnCallback(function (AuditEvidence $evidence): void {
            if ($this->fault === 'audit') {
                throw new RuntimeException('private-marker');
            }
            $this->audits[] = $evidence;
        });
        $this->reader = new ReadInvitationStatus($authority, $limiter, $queries);

        return new IssueInvitation(
            $authority,
            $limiter,
            new ConsoleAssignmentPolicy($queries),
            $users,
            $commands,
            $queries,
            $transactions,
            $audit
        );
    }
}
