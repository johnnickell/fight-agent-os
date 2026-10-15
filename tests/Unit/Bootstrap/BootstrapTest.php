<?php

declare(strict_types=1);

namespace Tests\Unit\Bootstrap;

use App\Adapter\Console\BootstrapSuperAdminCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Application\Bootstrap\BootstrapClaimScope;
use App\Application\Bootstrap\BootstrapCommandGuard;
use App\Application\Bootstrap\BootstrapOperation;
use App\Application\Bootstrap\CommandHandler\BootstrapSuperAdminHandler;
use App\Application\Invitation\ConsoleAssignmentPolicy;
use App\Application\Invitation\ConsoleCommandIntent;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Bootstrap\BootstrapClaims;
use App\Domain\Bootstrap\BootstrapEvidence;
use App\Domain\Bootstrap\BootstrapState;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use App\Domain\Invitation\ConsoleInvitationInput;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPermissionPlanItem;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyChangeAction;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyPlan;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedRolePlanItem;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Protects permanent claim sequencing, failure facts, bounded attribution and explicit confirmation
 */
final class BootstrapTest extends TestCase
{
    private BootstrapState $state;
    /** @var list<class-string> */
    private array $effects = [];

    /**
     * Sequences only the winning claim and exact managed elevation through guarded buses
     */
    public function testSuccessAndPermanentRefusal(): void
    {
        $command = $this->command();
        $operation = $this->operation();
        self::assertTrue($operation->run($command)->isComplete());
        self::assertSame([
            ReconcileManagedPolicy::class, InvitePendingUser::class, AssignRoleToUser::class
        ], $this->effects);
        self::assertSame('unavailable', $operation->run($command)->claim);
        self::assertCount(4, $this->effects); // Policy may still reconcile; invitation/assignment never repeat.
        self::assertSame($command->toArray(), BootstrapSuperAdmin::fromArray($command->toArray())->toArray());
        self::assertSame('bootstrap:'.$command->input->operatorId, BootstrapEvidence::claimed($command)->actorId());
        self::assertSame('user.bootstrap_claimed', BootstrapEvidence::claimed($command)->action());
        self::assertSame('user.bootstrap_completed', BootstrapEvidence::completed($command)->action());
        self::assertTrue(BootstrapEvidence::completed($command)->subjectId()->equals($command->userId));
        self::assertSame([], BootstrapEvidence::completed($command)->context());
    }

    /**
     * Reports independently retained steps instead of inferring rollback from exceptions
     */
    #[DataProvider('failures')]
    public function testFailures(string $phase, string $claim, string $invitation, string $assignment): void
    {
        $operation = $this->operation($phase);
        $result = $operation->run($this->command());
        self::assertFalse($result->isComplete());
        self::assertSame($claim, $result->claim);
        self::assertSame($invitation, $result->invitation);
        self::assertSame($assignment, $result->assignment);
        self::assertSame('unconfirmed', $result->audit);
    }

    /**
     * Supplies precommit and postcommit interruptions at each owned boundary
     *
     * @return iterable<array{string, string, string, string}>
     */
    public static function failures(): iterable
    {
        yield ['policy', 'absent', 'unknown', 'unknown'];
        yield ['role', 'absent', 'unknown', 'unknown'];
        yield ['claim', 'absent', 'unknown', 'unknown'];
        yield ['claim_ack', 'claimed', 'missing', 'missing'];
        yield ['invite', 'claimed', 'missing', 'missing'];
        yield ['invite_event', 'claimed', 'confirmed', 'missing'];
        yield ['assign', 'claimed', 'confirmed', 'missing'];
        yield ['assign_event', 'claimed', 'confirmed', 'confirmed'];
        yield ['audit', 'claimed', 'confirmed', 'confirmed'];
        yield ['inspection', 'unknown', 'unknown', 'unknown'];
        yield ['wrong_outcome', 'claimed', 'confirmed', 'missing'];
    }

    /**
     * Rejects reuse outside scoped invocation and overlapping invocations without dispatch
     */
    public function testInvocationIsolation(): void
    {
        $command = $this->command();
        $operation = $this->operation();
        try {
            $operation->perform($command);
            self::fail('Unscoped bootstrap must refuse.');
        } catch (LogicException) {
            self::assertSame([], $this->effects);
        }
        $operation = $this->operation(observer: static function (BootstrapOperation $operation) use ($command): void {
            try {
                $operation->run($command);
                self::fail('Overlapping bootstrap must refuse.');
            } catch (LogicException) {
                // The outer invocation remains usable and its own claim is the only winner.
            }
        });
        self::assertTrue($operation->run($command)->isComplete());
    }

    /**
     * Rejects untrusted message types and invalid serialized authority
     */
    public function testInvalidMessages(): void
    {
        $handler = new BootstrapSuperAdminHandler($this->operation());
        self::assertSame(BootstrapSuperAdmin::class, $handler::commandRegistration());
        $this->expectException(InvalidArgumentException::class);
        $handler->handle(CommandMessage::create(new ReconcileManagedPolicy(InstallationManagedPolicy::create())));
    }

    /**
     * Rejects malformed serialized authority
     */
    public function testInvalidSerialization(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BootstrapSuperAdmin::fromArray([]);
    }

    /**
     * Rejects self-attributed bootstrap targets
     */
    public function testSelfTarget(): void
    {
        $command = $this->command();
        $this->expectException(InvalidArgumentException::class);
        new BootstrapSuperAdmin($command->input, $command->input->operatorId);
    }

    /**
     * Requires explicit confirmation even in interactive invocations and sanitizes arbitrary parser input
     *
     * @phpstan-param list<string> $extra
     */
    #[DataProvider('invalidConsole')]
    public function testInvalidConsole(array $extra): void
    {
        $application = new SafeConsoleApplication();
        $application->setAutoExit(false);
        $application->addCommand(new BootstrapSuperAdminCommand($this->operation()));
        $output = new BufferedOutput();
        $exit = $application->run(new ArgvInput(['console', 'user:bootstrap-super-admin', ...$extra, '-vvv']), $output);
        self::assertSame(2, $exit);
        self::assertStringNotContainsString('private-marker', $output->fetch());
        self::assertSame([], $this->effects);
    }

    /**
     * Supplies missing, ambiguous and private operation selectors
     *
     * @return iterable<array{list<string>}>
     */
    public static function invalidConsole(): iterable
    {
        $valid = ['--internal', '--confirm-bootstrap', '--operator='.UserId::generate(), '--email=user@example.test'];
        yield [[]];
        yield [array_slice($valid, 1)];
        yield [[$valid[0], ...array_slice($valid, 2)]];
        yield [[...$valid, '--confirm-bootstrap']];
        yield [[...$valid, '--email=private-marker']];
        yield [[...$valid, '--password=private-marker']];
        yield [['--internal', '--confirm-bootstrap', '--operator=private-marker', '--email=user@example.test']];
    }

    /**
     * Presents safe success, partial and unknown state with a permanent recovery limitation
     */
    public function testConsoleOutcomes(): void
    {
        foreach (['', 'assign', 'inspection'] as $phase) {
            $tester = new CommandTester(new BootstrapSuperAdminCommand($this->operation($phase)));
            self::assertSame($phase === '' ? 0 : 1, $tester->execute([
                '--internal'          => true,
                '--confirm-bootstrap' => true,
                '--operator'          => (string) UserId::generate(),
                '--email'             => 'User@EXAMPLE.test'
            ]));
            $result = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($phase === '' ? 'bootstrap_complete' : 'partial_or_uncertain', $result['status']);
            self::assertSame($phase !== 'inspection', isset($result['user_id']));
            self::assertStringNotContainsString('private-marker', $tester->getDisplay());
        }
    }

    /**
     * Rejects bootstrap mutation without this invocation's acknowledged claim
     */
    public function testClaimScopeIsolation(): void
    {
        $scope = new BootstrapClaimScope();
        foreach (['requireClaim', 'acknowledgeClaim'] as $method) {
            try {
                $scope->$method();
                self::fail('Closed scope must refuse.');
            } catch (LogicException $error) {
                self::assertNull($error->getPrevious());
            }
        }
        $scope->open($this->command());
        try {
            $scope->open($this->command());
            self::fail('Overlapping scope must refuse.');
        } catch (LogicException $error) {
            self::assertNull($error->getPrevious());
        }
        try {
            $scope->requireClaim();
            self::fail('Unacknowledged scope must refuse.');
        } catch (LogicException $error) {
            self::assertNull($error->getPrevious());
        }
        $scope->acknowledgeClaim();
        $scope->requireClaim();
        $scope->close();
        $this->expectException(LogicException::class);
        $scope->requireClaim();
    }

    /**
     * Rejects changed claimed references, out-of-order assignment and repeated package mutations
     */
    #[DataProvider('scopeMutations')]
    public function testExactClaimScope(string $case): void
    {
        $command = $this->command();
        $scope = new BootstrapClaimScope();
        $scope->open($command);
        $scope->acknowledgeClaim();
        $invitation = new InvitePendingUser($command->input->actorId(), $command->userId, $command->input->email);
        if (str_starts_with($case, 'assign_') || $case === 'repeat_invite') {
            $scope->authorizeInvitation($invitation);
        }
        $assignment = new AssignRoleToUser(
            $command->input->operatorId,
            $command->userId,
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID)
        );
        if ($case === 'assign_repeat') {
            $scope->authorizeAssignment($assignment);
        }
        $this->expectException(LogicException::class);
        if (str_starts_with($case, 'invite_') || $case === 'repeat_invite') {
            $scope->authorizeInvitation(new InvitePendingUser(
                $case === 'invite_actor' ? 'console:'.UserId::generate() : $invitation->getActorId(),
                $case === 'invite_target' ? UserId::generate() : $invitation->getUserId(),
                $case === 'invite_email' ? EmailAddress::fromString('wrong@example.test') : $invitation->getEmail()
            ));
        } else {
            $scope->authorizeAssignment(new AssignRoleToUser(
                $case === 'assign_actor' ? UserId::generate() : $assignment->getActorId(),
                $case === 'assign_target' ? UserId::generate() : $assignment->getTargetUserId(),
                $case === 'assign_role' ? RoleId::generate() : $assignment->getRoleId()
            ));
        }
    }

    /**
     * Supplies claim-bound payload changes and single-use ordering violations
     *
     * @return iterable<array{string}>
     */
    public static function scopeMutations(): iterable
    {
        $cases = ['invite_actor', 'invite_target', 'invite_email', 'repeat_invite', 'before_invite',
            'assign_actor', 'assign_target', 'assign_role', 'assign_repeat'];
        foreach ($cases as $case) {
            yield [$case];
        }
    }

    /**
     * Builds a behavior seam without reimplementing upstream identity or transaction logic
     *
     * @phpstan-param (callable(BootstrapOperation): void)|null $observer
     */
    private function operation(string $failure = '', ?callable $observer = null): BootstrapOperation
    {
        $this->state = new BootstrapState('absent');
        $this->effects = [];
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(static function ($query) use ($failure) {
            if ($query instanceof GetRoleById) {
                return $failure === 'role' ? null : new RoleView(
                    $query->getRoleId(),
                    RoleName::fromString('ROLE_SUPER_ADMIN'),
                    true,
                    []
                );
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
        $claims = $this->createStub(BootstrapClaims::class);
        $claims->method('claim')->willReturnCallback(function () use ($failure): void {
            if ($failure === 'claim' || $this->state->claim !== 'absent') {
                throw new RuntimeException('private-marker');
            }
            $this->state = new BootstrapState('claimed', 'missing', 'missing');
            if ($failure === 'claim_ack') {
                throw new RuntimeException('private-marker');
            }
        });
        $claims->method('complete')->willReturnCallback(function () use ($failure): void {
            if ($failure === 'audit') {
                throw new RuntimeException('private-marker');
            }
            $this->state = new BootstrapState('claimed', 'confirmed', 'confirmed', 'confirmed');
        });
        $claims->method('inspect')->willReturnCallback(function () use ($failure): BootstrapState {
            if ($failure === 'inspection') {
                throw new RuntimeException('private-marker');
            }

            return $this->state;
        });
        $intent = new ConsoleCommandIntent();
        $router = $this->createStub(SynchronousCommandBus::class);
        $bus = new CommandPipeline($router);
        $scope = new BootstrapClaimScope();
        $bus->addFilter(new BootstrapCommandGuard($intent, new ConsoleAssignmentPolicy($queries), $scope));
        $operation = new BootstrapOperation($bus, $queries, $claims, $intent, $scope);
        $router->method('dispatch')->willReturnCallback(function (CommandMessage $message) use (
            $operation,
            $failure,
            $observer
        ): void {
            $command = $message->payload();
            if ($command instanceof BootstrapSuperAdmin) {
                new BootstrapSuperAdminHandler($operation)->handle($message);

                return;
            }
            assert($command instanceof Command);
            $this->effects[] = $command::class;
            if ($command instanceof ReconcileManagedPolicy) {
                if ($observer !== null) {
                    $observer($operation);
                }
                if ($failure === 'policy') {
                    throw new RuntimeException('private-marker');
                }
            }
            if ($command instanceof InvitePendingUser) {
                if ($failure === 'invite') {
                    throw new RuntimeException('private-marker');
                }
                self::assertSame('claimed', $this->state->claim);
                $this->state = new BootstrapState('claimed', 'confirmed', 'missing');
                if ($failure === 'invite_event') {
                    throw new RuntimeException('private-marker');
                }
            }
            if ($command instanceof AssignRoleToUser) {
                self::assertSame(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID, $command->getRoleId()->toString());
                if ($failure === 'assign') {
                    throw new RuntimeException('private-marker');
                }
                $this->state = new BootstrapState(
                    'claimed',
                    'confirmed',
                    $failure === 'wrong_outcome' ? 'missing' : 'confirmed'
                );
                if ($failure === 'assign_event') {
                    throw new RuntimeException('private-marker');
                }
            }
        });

        return $operation;
    }

    /**
     * Creates independently generated operator and target identities
     */
    private function command(): BootstrapSuperAdmin
    {
        return new BootstrapSuperAdmin(
            new ConsoleInvitationInput((string) UserId::generate(), 'first@example.test'),
            UserId::generate()
        );
    }
}
