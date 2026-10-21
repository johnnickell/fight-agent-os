<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Authorization\InvitationCorrectionAuthorization;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\InvitationRecoveryUncertain;
use App\Application\Failure\PermissionDenied;
use App\Application\Failure\ResourceNotFound;
use App\Application\Invitation\InvitationRateLimiter;
use App\Application\Invitation\RecoverInvitation;
use App\Domain\Invitation\InvitationOperation;
use App\Domain\Invitation\InvitationRatePolicy;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationCredential;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\ResendInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\InvitationAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
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
use Fight\AccessControl\Domain\AccessControl\User\Command\CorrectPendingInvitation;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\AccessClock;

/**
 * Protects exact recovery authority, safe outcome uncertainty and operation-specific admission policy
 */
final class InvitationRecoveryTest extends TestCase
{
    private User $actor;
    private User $target;
    private AuthenticationContext $authentication;
    private InvitationCorrectionAuthorization $callback;
    private string $fault = '';
    /** @var list<InvitationOperation> */
    private array $admissions = [];

    /**
     * Refuses incomplete actor, Role, Permission or target authority without retaining private diagnostics
     */
    #[DataProvider('authorityFaults')]
    public function testCallbackFailure(string $fault): void
    {
        $this->fault = $fault;
        $this->recovery();
        try {
            $this->callback->assertCanCorrectInvitation($this->actor->getId(), $this->target->getId());
            self::fail('Incomplete authority was accepted.');
        } catch (InvitationAdministrationAuthorizationException $failure) {
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString('private-marker', $failure->getMessage());
        }
    }

    /**
     * Supplies unknown, mismatched, stale, name-only and unavailable authority references
     *
     * @return iterable<array{string}>
     */
    public static function authorityFaults(): iterable
    {
        foreach (
            ['actor-missing', 'actor-wrong', 'inactive', 'role-extra', 'role-missing', 'permission-extra',
            'permission-missing', 'no-right', 'target-missing', 'target-wrong', 'store'] as $fault
        ) {
            yield [$fault];
        }
    }

    /**
     * Requires the exact management permission for each operation independently of other invitation rights
     */
    #[DataProvider('permissionMasks')]
    public function testPermissions(int $mask, string $operation): void
    {
        $recovery = $this->recovery($mask);
        if (($mask & 4) === 0) {
            $this->expectException(PermissionDenied::class);
        }
        $view = match ($operation) {
            'retry' => $recovery->retry($this->authentication, $this->target->getId()),
            'resend' => $recovery->resend($this->authentication, $this->target->getId()),
            default => $recovery->correct(
                $this->authentication,
                $this->target->getId(),
                EmailAddress::fromString('Corrected@EXAMPLE.test')
            )
        };
        self::assertTrue($view->getUserId()->equals($this->target->getId()));
        self::assertSame([InvitationOperation::from($operation)], $this->admissions);
    }

    /**
     * Supplies all three invitation-permission combinations for every recovery entry point
     *
     * @return iterable<array{int, string}>
     */
    public static function permissionMasks(): iterable
    {
        foreach (range(0, 7) as $mask) {
            foreach (['retry', 'resend', 'correct'] as $operation) {
                yield [$mask, $operation];
            }
        }
    }

    /**
     * Refuses silent dispatch vetoes, missing effects and unsafe query results rather than inventing success
     *
     * @param string $fault
     * @param string $exception
     *
     * @phpstan-param class-string<\Throwable> $exception
     */
    #[DataProvider('dispatchFaults')]
    public function testDispatchFailure(string $fault, string $exception): void
    {
        $this->fault = $fault;
        $recovery = $this->recovery();
        $this->expectException($exception);
        $recovery->retry($this->authentication, $this->target->getId());
    }

    /**
     * Supplies missing work, post-commit uncertainty and untrustworthy status projections
     *
     * @return iterable<array{string, class-string<\Throwable>}>
     */
    public static function dispatchFaults(): iterable
    {
        yield ['grant-missing', ResourceNotFound::class];
        yield ['no-effect', LogicException::class];
        yield ['after-missing', LogicException::class];
        yield ['view-missing', LogicException::class];
        yield ['view-wrong', LogicException::class];
        yield ['uncertain', InvitationRecoveryUncertain::class];
    }

    /**
     * Rejects an unrelated grant revision as proof that a correction command actually replaced authority
     */
    #[DataProvider('wrongCorrectionEffects')]
    public function testWrongRecoveryEffect(string $fault): void
    {
        $this->fault = $fault;
        $recovery = $this->recovery();
        $this->expectException(InvitationRecoveryUncertain::class);
        $recovery->correct(
            $this->authentication,
            $this->target->getId(),
            EmailAddress::fromString('corrected@example.test')
        );
    }

    /**
     * Supplies unrelated state changes and missing or mismatched safe identity evidence
     *
     * @return iterable<array{string}>
     */
    public static function wrongCorrectionEffects(): iterable
    {
        foreach (['wrong-effect', 'wrong-email', 'wrong-user', 'missing-user'] as $fault) {
            yield [$fault];
        }
    }

    /**
     * Reports replacement during retry as uncertain instead of claiming same-generation retry evidence
     */
    public function testReplacedDuringRetry(): void
    {
        $this->fault = 'retry-replaced';
        $recovery = $this->recovery();
        $this->expectException(InvitationRecoveryUncertain::class);
        $recovery->retry($this->authentication, $this->target->getId());
    }

    /**
     * Rejects disabling or unbounded recovery budgets
     */
    #[DataProvider('invalidBudgets')]
    public function testInvalidBudget(int $retry, int $resend, int $correct): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InvitationRatePolicy(retryLimit: $retry, resendLimit: $resend, correctLimit: $correct);
    }

    /**
     * Supplies lower and upper bounds for all recovery operations
     *
     * @return iterable<array{int, int, int}>
     */
    public static function invalidBudgets(): iterable
    {
        yield [0, 1, 1];
        yield [1001, 1, 1];
        yield [1, 0, 1];
        yield [1, 1001, 1];
        yield [1, 1, 0];
        yield [1, 1, 1001];
    }

    /**
     * Builds a real principal resolver and callback with controlled authoritative references and observed bus effects
     */
    private function recovery(int $mask = 7): RecoverInvitation
    {
        $now = new DateTimeImmutable('2026-10-19T12:00:00Z');
        $definitions = [];
        foreach (['INVITE_USERS', 'ASSIGN_SUPER_ADMIN', 'MANAGE_USER_INVITATIONS'] as $bit => $name) {
            if (($mask & (1 << $bit)) !== 0 && !($name === 'MANAGE_USER_INVITATIONS' && $this->fault === 'no-right')) {
                $definitions[] = Permission::defineManaged(
                    PermissionId::generate(),
                    PermissionName::fromString($name),
                    PermissionTier::SUPER_ADMIN_ONLY,
                    $now
                );
            }
        }
        $role = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString('ROLE_SUPER_ADMIN'),
            array_map(static fn(Permission $p) => $p->getId(), $definitions),
            $now
        );
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('actor@example.test'), $now);
        $this->actor->activate(PasswordHash::fromString((string) password_hash('fixture', PASSWORD_BCRYPT)), $now);
        $this->actor->assignRole($role->getId(), $now);
        $this->target = User::invite(UserId::generate(), EmailAddress::fromString('target@example.test'), $now);
        if ($this->fault === 'inactive') {
            $this->actor->disable($now);
        }
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturnCallback(function (UserId $id): ?User {
            if ($this->fault === 'store') {
                throw new RuntimeException('private-marker');
            }
            if ($id->equals($this->actor->getId())) {
                return match ($this->fault) {
                    'actor-missing' => null,
                    'actor-wrong' => $this->target,
                    default => $this->actor
                };
            }

            return match ($this->fault) {
                'target-missing' => null,
                'target-wrong' => $this->actor,
                default => $this->target
            };
        });
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByName')->willReturn($role);
        $roles->method('getByIds')->willReturn(match ($this->fault) {
            'role-missing' => [],
            'role-extra' => [$role, $role],
            default => [$role]
        });
        $permissions = $this->createStub(PermissionRepository::class);
        $permissions->method('getByIds')->willReturn(match ($this->fault) {
            'permission-missing' => [],
            'permission-extra' => [...$definitions, $definitions[0] ?? throw new LogicException('Missing fixture.')],
            default => $definitions
        });
        $installation = new InstallationAuthority($users, $roles, $permissions);
        $this->callback = new InvitationCorrectionAuthorization($installation);
        $sessions = $this->createStub(RefreshSessionRepository::class);
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
        $sessions->method('getById')->willReturn($session);
        $this->authentication = new AuthenticationContext(
            $this->actor->getId(),
            $session->getId(),
            $this->actor->getAuthenticationVersion()
        );
        $authority = new AuthenticatedPermissions(new AuthoritativePrincipalResolver(
            $users,
            $sessions,
            $roles,
            $permissions,
            new AccessClock($now)
        ), new PermissionGuard());
        $limiter = $this->createStub(InvitationRateLimiter::class);
        $limiter->method('consume')->willReturnCallback(function (
            UserId $actor,
            InvitationOperation $operation,
            ?UserId $target
        ): void {
            self::assertTrue($this->actor->getId()->equals($actor));
            self::assertNotNull($target);
            self::assertTrue($this->target->getId()->equals($target));
            $this->admissions[] = $operation;
        });
        $initial = ActivationGrant::issue(
            \Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin::PRIVATE_INVITATION,
            $this->target->getId(),
            ActivationCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+7 days'),
            $this->target->getEmail(),
            'retained-unit-material'
        );
        $claim = CredentialDeliveryClaimToken::generate();
        $before = $initial->claimDelivery($claim, $now, $now->modify('+1 minute'))->failDelivery(
            $claim,
            $now,
            CredentialDeliveryFailure::RETRYABLE_PROVIDER
        );
        $after = $before;
        $grants = $this->createStub(ActivationGrantRepository::class);
        $grants->method('getLatestByUserId')->willReturnCallback(function () use (&$after): ?ActivationGrant {
            return $this->fault === 'grant-missing' ? null : $after;
        });
        $replacement = ActivationGrant::issue(
            \Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin::PRIVATE_INVITATION,
            $this->target->getId(),
            ActivationCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+7 days'),
            EmailAddress::fromString('corrected@example.test'),
            'replacement-unit-material'
        );
        $commands = $this->createStub(CommandBus::class);
        $dispatch = function ($command) use (&$after, $before, $replacement): void {
            if ($command instanceof CorrectPendingInvitation) {
                self::assertSame('corrected@example.test', $command->getEmail()->toString());
            }
            if ($this->fault === 'uncertain') {
                throw new EventDispatchFailed([]);
            }
            $replaces = $command instanceof ResendInvitationDelivery || $command instanceof CorrectPendingInvitation;
            $expected = $replaces ? $replacement : $before->requestDeliveryRetry();
            $after = match ($this->fault) {
                'no-effect' => $before,
                'after-missing' => null,
                'wrong-effect' => $before->requestDeliveryRetry(),
                'retry-replaced' => $replacement,
                default => $expected
            };
        };
        $commands->method('execute')->willReturnCallback($dispatch);
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(function ($query) use ($now) {
            if ($query instanceof GetUserById) {
                return $this->fault === 'missing-user' ? null : new UserView(
                    $this->fault === 'wrong-user' ? UserId::generate() : $this->target->getId(),
                    EmailAddress::fromString(
                        $this->fault === 'wrong-email' ? 'other@example.test' : 'corrected@example.test'
                    ),
                    UserState::PENDING_ACTIVATION,
                    [],
                    $now,
                    $now
                );
            }

            return $this->fault === 'view-missing' ? null : new InvitationDeliveryStatusView(
                $this->fault === 'view-wrong' ? UserId::generate() : $this->target->getId(),
                CredentialDeliveryStatus::PENDING,
                $now->modify('+7 days'),
                \Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId::generate()
            );
        });

        return new RecoverInvitation($authority, $installation, $limiter, $grants, $commands, $queries);
    }
}
