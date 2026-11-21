<?php

declare(strict_types=1);

namespace Tests\Unit\Bootstrap;

use App\Adapter\Console\BootstrapSuperAdminCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Application\Authorization\CommandHandler\BootstrapSuperAdminHandler;
use App\Application\Authorization\IncompleteBootstrap;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Authorization\MembershipEvidenceRepository;
use App\Domain\Authorization\TrustedOperator;
use App\Domain\Bootstrap\BootstrapEvidence;
use App\Domain\Bootstrap\BootstrapProgress;
use App\Domain\Bootstrap\BootstrapState;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
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
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Protects manual admission, retained partial outcomes, attribution and explicit confirmation
 */
final class BootstrapTest extends TestCase
{
    private BootstrapState $state;
    private BootstrapProgress $bootstrapProgress;
    private bool $superAdmin = false;
    /** @var list<class-string> */
    private array $effects = [];

    /**
     * Refuses a second invocation only once authoritative Super Admin membership exists
     */
    public function testSuccessAndExistingAdminRefusal(): void
    {
        $command = $this->command();
        $operation = $this->operation();
        self::assertTrue($this->runBootstrap($operation, $command)->isComplete());
        self::assertSame([
            ReconcileManagedPolicy::class, InvitePendingUser::class, AssignRoleToUser::class
        ], $this->effects);
        self::assertSame('refused', $this->runBootstrap($operation, $this->command())->admission);
        self::assertCount(3, $this->effects);
        self::assertSame($command->toArray(), BootstrapSuperAdmin::fromArray($command->toArray())->toArray());
        $evidence = BootstrapEvidence::completed($command);
        self::assertSame('bootstrap:'.$command->operator->operatorId, $evidence->actorId);
        self::assertTrue($evidence->userId->equals($command->userId));
    }

    /**
     * Refuses a failed presence query without attempting policy, invitation or assignment
     */
    public function testPresenceQueryFailure(): void
    {
        $operation = $this->operation('presence');
        self::assertSame('unknown', $this->runBootstrap($operation, $this->command())->admission);
        self::assertSame([], $this->effects);
    }

    /**
     * Reports retained independent steps rather than inferring rollback from exceptions
     */
    #[DataProvider('failures')]
    public function testFailures(string $phase, string $invitation, string $assignment): void
    {
        $result = $this->runBootstrap($this->operation($phase), $this->command());
        self::assertFalse($result->isComplete());
        self::assertSame('admitted', $result->admission);
        self::assertSame($invitation, $result->invitation);
        self::assertSame($assignment, $result->assignment);
        self::assertSame('unconfirmed', $result->audit);
        if (in_array($phase, ['preview', 'role', 'wrong-role'], true)) {
            self::assertSame([ReconcileManagedPolicy::class], $this->effects);
        }
    }

    /**
     * Supplies precommit and postcommit failures at the supported effect boundaries
     *
     * @return iterable<array{string, string, string}>
     */
    public static function failures(): iterable
    {
        yield ['policy', 'missing', 'missing'];
        yield ['preview', 'missing', 'missing'];
        yield ['role', 'missing', 'missing'];
        yield ['wrong-role', 'missing', 'missing'];
        yield ['invite', 'missing', 'missing'];
        yield ['invite_event', 'confirmed', 'missing'];
        yield ['assign', 'confirmed', 'missing'];
        yield ['assign_event', 'confirmed', 'confirmed'];
        yield ['assign_veto', 'confirmed', 'missing'];
        yield ['audit', 'confirmed', 'confirmed'];
        yield ['inspection', 'unknown', 'unknown'];
    }

    /**
     * Admits another manual request after a partial attempt without Super Admin membership
     */
    public function testPartialAttemptDoesNotConsumeAdmission(): void
    {
        $operation = $this->operation('assign');
        self::assertSame('missing', $this->runBootstrap($operation, $this->command())->assignment);
        self::assertSame('admitted', $this->runBootstrap($operation, $this->command())->admission);
        self::assertCount(6, $this->effects);
    }

    /**
     * Rejects a different command payload before any package work
     */
    public function testRejectsOtherCommandPayload(): void
    {
        $operation = $this->operation();
        $command = $this->command();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unexpected bootstrap command.');
        $operation->execute(new InvitePendingUser(
            \Fight\AccessControl\Domain\AccessControl\Audit\SystemId::generate(),
            $command->userId,
            $command->email
        ));
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
        new BootstrapSuperAdmin($command->operator, $command->email, $command->operator->operatorId);
    }

    /**
     * Requires explicit confirmation and sanitizes arbitrary parser input
     *
     * @phpstan-param list<string> $extra
     */
    #[DataProvider('invalidConsole')]
    public function testInvalidConsole(array $extra): void
    {
        $application = new SafeConsoleApplication();
        $application->setAutoExit(false);
        $application->addCommand(new BootstrapSuperAdminCommand($this->operation(), $this->bootstrapProgress));
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
     * Presents safe step facts and manual retry guidance without a permanent claim
     */
    public function testConsoleOutcomes(): void
    {
        foreach (['', 'assign', 'inspection', 'presence'] as $phase) {
            $commandBus = $this->operation($phase);
            $tester = new CommandTester(new BootstrapSuperAdminCommand($commandBus, $this->bootstrapProgress));
            self::assertSame($phase === '' ? 0 : 1, $tester->execute([
                '--internal'          => true,
                '--confirm-bootstrap' => true,
                '--operator'          => (string) UserId::generate(),
                '--email'             => 'User@EXAMPLE.test'
            ]));
            $result = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($phase === '' ? 'bootstrap_complete' : 'partial_or_uncertain', $result['status']);
            self::assertSame($phase === 'presence' ? 'unknown' : 'admitted', $result['admission']);
            self::assertSame(in_array($phase, ['', 'assign'], true), isset($result['user_id']));
            self::assertArrayNotHasKey('claim', $result);
            self::assertStringNotContainsString('private-marker', $tester->getDisplay());
        }
    }

    /**
     * Redacts unavailable inspection after a successful void dispatch
     */
    public function testConsoleRedactsInspectionException(): void
    {
        $progress = $this->createStub(BootstrapProgress::class);
        $progress->method('inspect')->willThrowException(new RuntimeException('private-inspection-diagnostic'));
        $tester = new CommandTester(new BootstrapSuperAdminCommand($this->createStub(CommandBus::class), $progress));
        self::assertSame(1, $tester->execute([
            '--internal'          => true,
            '--confirm-bootstrap' => true,
            '--operator'          => (string) UserId::generate(),
            '--email'             => 'user@example.test'
        ]));
        self::assertSame(
            'Bootstrap outcome is uncertain; inspect trusted state before another manual attempt.'."\n",
            $tester->getDisplay()
        );
    }

    /**
     * Observes owned policy and safe outcomes without copying package transaction behavior
     */
    private function operation(string $failure = ''): CommandBus
    {
        $this->state = new BootstrapState('admitted', 'missing', 'missing');
        $this->superAdmin = false;
        $this->effects = [];
        $users = $this->createStub(UserRepository::class);
        $users->method('hasRoleAssignment')->willReturnCallback(function () use ($failure): bool {
            if ($failure === 'presence') {
                throw new RuntimeException('private-marker');
            }

            return $this->superAdmin;
        });
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(static function ($query) use ($failure) {
            if ($query instanceof GetRoleById) {
                return $failure === 'role' ? null : new RoleView(
                    $failure === 'wrong-role' ? RoleId::generate() : $query->getRoleId(),
                    RoleName::fromString('ROLE_SUPER_ADMIN'),
                    true,
                    [],
                    \Fight\AccessControl\Domain\AccessControl\Role\RoleRevision::initial()
                );
            }
            if ($failure === 'preview') {
                return null;
            }
            $policy = (new \App\Adapter\Authorization\JsonManagedPolicy())->load();

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
        $progress = $this->createStub(BootstrapProgress::class);
        $this->bootstrapProgress = $progress;
        $membershipEvidenceRepository = $this->createStub(MembershipEvidenceRepository::class);
        $membershipEvidenceRepository->method('add')->willReturnCallback(function () use ($failure): void {
            if ($failure === 'audit') {
                throw new RuntimeException('private-marker');
            }
            $this->state = new BootstrapState('admitted', 'confirmed', 'confirmed', 'confirmed');
        });
        $progress->method('inspect')->willReturnCallback(function () use ($failure): BootstrapState {
            if ($failure === 'inspection') {
                throw new RuntimeException('private-marker');
            }

            return $this->state;
        });
        $bus = $this->createStub(CommandBus::class);
        $bus->method('execute')->willReturnCallback(function (Command $command) use ($failure): void {
            $this->effects[] = $command::class;
            if ($command instanceof ReconcileManagedPolicy && $failure === 'policy') {
                throw new RuntimeException('private-marker');
            }
            if ($command instanceof InvitePendingUser) {
                if ($failure === 'invite') {
                    throw new RuntimeException('private-marker');
                }
                $this->state = new BootstrapState('admitted', 'confirmed', 'missing');
                if ($failure === 'invite_event') {
                    throw new RuntimeException('private-marker');
                }
            }
            if ($command instanceof AssignRoleToUser) {
                self::assertSame(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID, $command->getRoleId()->toString());
                if ($failure === 'assign') {
                    throw new RuntimeException('private-marker');
                }
                if ($failure === 'assign_veto') {
                    return;
                }
                $this->superAdmin = true;
                $this->state = new BootstrapState('admitted', 'confirmed', 'confirmed');
                if ($failure === 'assign_event') {
                    throw new RuntimeException('private-marker');
                }
            }
        });

        $transactionalUnitOfWork = $this->createStub(TransactionalUnitOfWork::class);
        $transactionalUnitOfWork->method('commitTransactional')->willReturnCallback(
            static fn(callable $operation): mixed => $operation()
        );

        $handler = new BootstrapSuperAdminHandler(
            $bus,
            $queries,
            $users,
            $progress,
            new \App\Adapter\Authorization\JsonManagedPolicy(),
            $transactionalUnitOfWork,
            $membershipEvidenceRepository
        );
        $commandBus = $this->createStub(CommandBus::class);
        $commandBus->method('execute')->willReturnCallback(
            static fn(Command $command) => $handler->handle(CommandMessage::create($command))
        );

        return $commandBus;
    }

    /**
     * Collects the command failure facts or inspects successful dispatch
     */
    private function runBootstrap(CommandBus $commandBus, BootstrapSuperAdmin $command): BootstrapState
    {
        try {
            $commandBus->execute($command);
        } catch (IncompleteBootstrap $failure) {
            return $failure->state;
        }

        return $this->bootstrapProgress->inspect($command);
    }

    /**
     * Creates independently generated operator and target identities
     */
    private function command(): BootstrapSuperAdmin
    {
        $operatorId = UserId::generate();

        return new BootstrapSuperAdmin(
            new TrustedOperator($operatorId, 'bootstrap:'.$operatorId->toString()),
            EmailAddress::fromString('first@example.test'),
            UserId::generate()
        );
    }
}
