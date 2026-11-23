<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Adapter\Http\Action\Api\V1\Invitations\IssueInvitationAction;
use App\Adapter\Http\Api\Invitation\InvitationOperation;
use App\Adapter\Http\Api\Invitation\InvitationRateLimiter;
use App\Adapter\Http\Api\Invitation\InvitationRatePolicy;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Responder\Api\V1\Invitations\IssueInvitationResponder;
use App\Application\Authorization\InstallationAssignmentPolicy;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\IncompleteInvitation;
use App\Application\Failure\InvitationConfirmationRequired;
use App\Application\Invitation\CommandHandler\IssueInvitationHandler;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Authorization\MembershipEvidenceRepository;
use App\Domain\Invitation\Command\IssueInvitation;
use App\Domain\Invitation\InvitationMode;
use App\Domain\Invitation\InvitationRoleEvidence;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\FindInvitationDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\CredentialDeliveryAbsent;
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
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use GuzzleHttp\Psr7\ServerRequest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessClock;
use UnexpectedValueException;

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
    /** @var list<InvitationRoleEvidence> */
    private array $audits = [];
    /** @var list<InvitationOperation> */
    private array $admissions = [];
    private string $fault = '';
    private int $issuanceDispatches = 0;

    /**
     * Rejects either missing trusted request context before admission or dispatch
     */
    #[DataProvider('requiredContexts')]
    public function testRejectsMissingRequestContext(string $missingContext): void
    {
        $issuer = $this->issuer();
        $this->expectException(AuthenticationRequired::class);
        try {
            $issuer(
                $this->authentication,
                EmailAddress::fromString('new@example.test'),
                InvitationMode::ORDINARY,
                false,
                $missingContext
            );
        } finally {
            self::assertSame([], $this->admissions);
            self::assertSame(0, $this->issuanceDispatches);
        }
    }

    /**
     * Supplies each context required by the issuance action
     *
     * @return iterable<string, array{string}>
     */
    public static function requiredContexts(): iterable
    {
        yield 'missing authentication' => [AuthenticationContext::class];
        yield 'missing security' => [SecurityContext::class];
    }

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
            $response = $issuer($this->authentication, EmailAddress::fromString('NEW@EXAMPLE.test'), $mode, true);
            self::assertTrue($allowed);
            self::assertSame(201, $response->getStatusCode());
            self::assertTrue($mode->roleId()->equals($this->audits[0]->roleId));
            self::assertTrue($this->actor->getId()->equals($this->audits[0]->actorId));
            self::assertInstanceOf(InvitePendingUser::class, $this->effects[0]);
            self::assertTrue($this->effects[0]->getUserId()->equals($this->audits[0]->userId));
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
            $issuer(
                $this->authentication,
                EmailAddress::fromString('new@example.test'),
                InvitationMode::SUPER_ADMIN,
                false
            );
            self::fail('Missing confirmation was accepted.');
        } catch (InvitationConfirmationRequired) {
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
        $issuer(
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
    public function testRetainedOutcomes(string $fault, string $assignment, bool $known, InvitationMode $mode): void
    {
        $this->fault = $fault;
        $issuer = $this->issuer();
        try {
            $issuer(
                $this->authentication,
                EmailAddress::fromString('new@example.test'),
                $mode,
                true
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
     * @return iterable<string, array{string, string, bool, InvitationMode}>
     */
    public static function failures(): iterable
    {
        foreach (
            [
            ['origin', 'unknown', false], ['origin-postcommit', 'unconfirmed', true],
            ['assignment', 'unconfirmed', true], ['assignment-postcommit', 'confirmed', true],
            ['audit', 'confirmed', true], ['query', 'unknown', false],
            ['wrong-email', 'unknown', false], ['wrong-state', 'unknown', false],
            ['delivery-null', 'confirmed', true], ['delivery-query', 'confirmed', true],
            ['audit-and-delivery-query', 'confirmed', true],
            ['veto-assignment', 'unconfirmed', true]
            ] as $case
        ) {
            foreach ([InvitationMode::ORDINARY, InvitationMode::SUPER_ADMIN] as $mode) {
                yield $case[0].$mode->value => [...$case, $mode];
            }
        }
    }

    /**
     * Distinguishes an invalid preview reply from a valid plan reporting drift before any invitation effect
     *
     * @phpstan-param class-string<\Throwable> $exception
     */
    #[DataProvider('installationPreviewFailures')]
    public function testInstallationPreviewFailure(string $fault, string $exception): void
    {
        $this->fault = $fault;
        $issuer = $this->issuer();
        $this->expectException($exception);
        try {
            $issuer(
                $this->authentication,
                EmailAddress::fromString('new@example.test'),
                InvitationMode::ORDINARY,
                false
            );
        } finally {
            self::assertSame([], $this->effects);
            self::assertSame([], $this->audits);
        }
    }

    /**
     * Supplies a missing reply, a wrong-type reply and a typed plan reporting drift
     *
     * @return iterable<string, array{string, class-string<\Throwable>}>
     */
    public static function installationPreviewFailures(): iterable
    {
        yield 'missing preview' => ['missing-preview', UnexpectedValueException::class];
        yield 'wrong preview type' => ['wrong-preview', UnexpectedValueException::class];
        yield 'valid drift plan' => ['policy', StateConflict::class];
        yield 'internal preview failure' => ['preview-fault', \LogicException::class];
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
        $issuer(
            $this->authentication,
            EmailAddress::fromString('new@example.test'),
            InvitationMode::ORDINARY,
            false
        );
    }

    /**
     * Supplies existing canonical address and concurrent duplicate failures
     *
     * @return iterable<array{string}>
     */
    public static function conflicts(): iterable
    {
        foreach (['duplicate', 'race'] as $fault) {
            yield [$fault];
        }
    }

    /**
     * Completes an admitted operation after revocation and requires fresh authority next time
     */
    public function testRevocationAfterAdmission(): void
    {
        $this->fault = 'revoke';
        $issuer = $this->issuer();
        $email = EmailAddress::fromString('new@example.test');
        self::assertSame(201, $issuer($this->authentication, $email, InvitationMode::ORDINARY, false)->getStatusCode());
        self::assertCount(1, $this->audits);
        $this->expectException(PermissionDenied::class);
        $issuer($this->authentication, $email, InvitationMode::ORDINARY, false);
    }

    /**
     * Rejects a different command without starting invitation work
     */
    public function testHandlerRejectsUnrelatedIntent(): void
    {
        $handler = new IssueInvitationHandler(
            $this->createStub(CommandBus::class),
            $this->createStub(QueryBus::class),
            $this->createStub(TransactionalUnitOfWork::class),
            $this->createStub(MembershipEvidenceRepository::class)
        );
        $this->expectException(InvalidArgumentException::class);
        $handler->handle(CommandMessage::create(new InvitePendingUser(
            UserId::generate(),
            UserId::generate(),
            EmailAddress::fromString('new@example.test')
        )));
    }

    /**
     * Rejects serialized invitation intent without a supplied target identity
     */
    public function testIntentRequiresSuppliedIdentity(): void
    {
        $intent = new IssueInvitation(
            UserId::generate(),
            UserId::generate(),
            EmailAddress::fromString('new@example.test'),
            InvitationMode::ORDINARY
        );
        $input = $intent->toArray();
        unset($input['user_id']);
        $this->expectException(InvalidArgumentException::class);
        IssueInvitation::fromArray($input);
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
    ): \Closure {
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
        $principalResolver = new AuthoritativePrincipalResolver(
            $users,
            $sessions,
            $roles,
            $permissionStore,
            new AccessClock($now)
        );
        $guard = new PermissionGuard();
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
                if (in_array($this->fault, ['delivery-query', 'audit-and-delivery-query'], true)) {
                    throw new RuntimeException('private-marker');
                }

                if ($this->fault === 'delivery-null') {
                    return new CredentialDeliveryAbsent();
                }

                return new InvitationDeliveryStatusView(
                    $query->getUserId(),
                    CredentialDeliveryStatus::PENDING,
                    $now->modify('+7 days'),
                    ActivationDeliveryId::generate(),
                    ActivationOrigin::PRIVATE_INVITATION
                );
            }
            if ($this->fault === 'missing-preview') {
                return null;
            }
            if ($this->fault === 'wrong-preview') {
                return 'private-marker';
            }
            if ($this->fault === 'preview-fault') {
                throw new \LogicException('Unexpected internal preview failure.');
            }
            $policy = (new \Tests\Support\InstallationCatalogFixture())->load();
            $permissionAction = ManagedPolicyChangeAction::UNCHANGED;
            if ($this->fault === 'policy') {
                $permissionAction = ManagedPolicyChangeAction::RECONCILE;
            }

            return new ManagedPolicyPlan(
                array_map(
                    static fn($d) => new ManagedPermissionPlanItem($d, $permissionAction),
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
                throw new StateConflict(previous: new DuplicateEmailException('private-marker'));
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
                $id,
                EmailAddress::fromString($this->fault === 'wrong-email' ? 'wrong@example.test' : 'new@example.test'),
                $this->fault === 'wrong-state' ? UserState::DISABLED : UserState::PENDING_ACTIVATION,
                $command instanceof AssignRoleToUser ? [$command->getRoleId()] : [],
                $now,
                $now
            );
            if ($this->fault === 'revoke' && $command instanceof InvitePendingUser) {
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
        $audit = $this->createStub(MembershipEvidenceRepository::class);
        $audit->method('add')->willReturnCallback(function (InvitationRoleEvidence $evidence): void {
            if (in_array($this->fault, ['audit', 'audit-and-delivery-query'], true)) {
                throw new RuntimeException('private-marker');
            }
            $this->audits[] = $evidence;
        });

        $handler = new IssueInvitationHandler($commands, $queries, $transactions, $audit);
        $handlers = new Container();
        $handlers->set(IssueInvitationHandler::class, static fn(): IssueInvitationHandler => $handler);
        $router = new ServiceAwareCommandRouter($handlers);
        $router->registerHandlers([IssueInvitationHandler::commandRegistration() => IssueInvitationHandler::class]);
        $routingBus = new RoutingCommandBus($router);
        $issuanceCommands = $this->createStub(CommandBus::class);
        $issuanceCommands->method('execute')->willReturnCallback(function (IssueInvitation $intent) use (
            $routingBus
        ): void {
            ++$this->issuanceDispatches;
            $routingBus->execute(IssueInvitation::fromArray($intent->toArray()));
        });
        $action = new IssueInvitationAction(
            $guard,
            $limiter,
            new InstallationAssignmentPolicy($queries, new \Tests\Support\InstallationCatalogFixture()),
            $users,
            $issuanceCommands,
            $queries,
            new IssueInvitationResponder(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()))
        );

        return static function (
            AuthenticationContext $authentication,
            EmailAddress $email,
            InvitationMode $mode,
            bool $confirmed,
            ?string $missingContext = null
        ) use (
            $action,
            $guard,
            $principalResolver
        ): ResponseInterface {
            try {
                $security = new SecurityContext($principalResolver->resolve($authentication));
            } catch (\Throwable) {
                throw new PermissionDenied();
            }
            $guard->assertAllowed($security, [PermissionName::fromString('INVITE_USERS')]);

            $request = (new ServerRequest('POST', '/api/v1/invitations'))
                ->withAttribute(AuthenticationContext::class, $authentication)
                ->withAttribute(SecurityContext::class, $security)
                ->withAttribute(JsonBody::class, [
                    'email' => $email->toString(), 'mode' => $mode->value, 'confirm_super_admin' => $confirmed
                ]);

            return $action->handle($missingContext === null ? $request : $request->withoutAttribute($missingContext));
        };
    }
}
