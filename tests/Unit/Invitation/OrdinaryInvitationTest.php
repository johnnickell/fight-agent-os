<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Adapter\Console\InviteUserCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\CredentialDelivery\RandomActivationCredentialGenerator;
use App\Application\Invitation\InvitationTransaction;
use App\Application\Invitation\InviteOrdinaryUserHandler;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use App\Domain\Invitation\ConsoleInvitationInput;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPermissionPlanItem;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyChangeAction;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyPlan;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedRolePlanItem;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Protects ordinary orchestration, bounded input and safe Symfony console presentation
 */
final class OrdinaryInvitationTest extends TestCase
{
    /**
     * Coordinates public package messages and required operator audit without choosing elevated authority
     */
    public function testInvitation(): void
    {
        $input = new ConsoleInvitationInput(UserId::generate()->toString(), 'New.User@EXAMPLE.test');
        $target = UserId::generate();
        $transaction = $this->createMock(InvitationTransaction::class);
        $transaction->expects(self::once())->method('commit')->willReturnCallback(static fn(callable $work) => $work());
        $queries = $this->createMock(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(
            fn($query) => $query instanceof GetUserById ? $this->view($target) : $this->plan()
        );
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::exactly(2))->method('execute')->willReturnCallback(
            static function ($command) use ($input, $target): void {
                if ($command instanceof InvitePendingUser) {
                    self::assertSame($target, $command->getUserId());
                    self::assertSame($input->actorId(), $command->getActorId());
                    self::assertSame('new.user@example.test', $command->getEmail()->toString());
                } else {
                    self::assertInstanceOf(AssignRoleToUser::class, $command);
                    self::assertSame($input->operatorId, $command->getActorId());
                    self::assertSame($target, $command->getTargetUserId());
                    self::assertSame(InstallationManagedPolicy::USER_ROLE_ID, $command->getRoleId()->toString());
                }
            }
        );
        $audit = $this->createMock(AuditEvidenceRepository::class);
        $audit->expects(self::once())->method('add')->with(self::callback(
            static fn(AuditEvidence $e): bool => $e->actorId() === $input->actorId()
                && $e->subjectId()->equals($target) && $e->action() === 'user.console_role_assigned'
                && $e->context() === []
        ));
        $handler = new InviteOrdinaryUserHandler($transaction, $queries, $commands, $audit);
        $message = new InviteOrdinaryUser($input, $target);
        $handler->handle(CommandMessage::create($message));
        self::assertSame($message->toArray(), InviteOrdinaryUser::fromArray($message->toArray())->toArray());
    }

    /**
     * Presents only the canonical ordinary result after real bus execution and a safe outcome read
     */
    public function testConsoleSuccess(): void
    {
        $commands = $this->createMock(CommandBus::class);
        $target = null;
        $commands->expects(self::once())->method('execute')->willReturnCallback(
            static function (InviteOrdinaryUser $command) use (&$target): void {
                self::assertSame('new.user@example.test', $command->input->email->toString());
                $target = $command->userId;
            }
        );
        $queries = $this->createMock(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(fn(GetUserById $query) => $this->view($query->getUserId()));
        [$exit, $output] = $this->console($commands, $queries, [
            '--email=New.User@EXAMPLE.test', '--operator='.UserId::generate()->toString(), '--internal'
        ]);
        self::assertSame(0, $exit);
        self::assertSame([
            'email' => 'new.user@example.test', 'user_id' => $target->toString(), 'status' => 'invited'
        ], json_decode($output, true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * Refuses malformed and authority-selecting input without dispatch or unsafe Symfony diagnostics
     *
     * @phpstan-param list<string> $arguments
     */
    #[DataProvider('invalidArguments')]
    public function testInvalidArguments(array $arguments): void
    {
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('execute');
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::never())->method('fetch');
        [$exit, $output] = $this->console($commands, $queries, [...$arguments, '-vvv']);
        self::assertSame(2, $exit);
        self::assertContains($output, [
            InviteUserCommand::USAGE."\n", 'Invalid console input; use ./bin/console help user:invite.'."\n"
        ]);
    }

    /**
     * Supplies malformed input and marker-bearing parser failures
     *
     * @return iterable<array{list<string>}>
     */
    public static function invalidArguments(): iterable
    {
        $operator = '--operator='.UserId::generate()->toString();
        yield [[]];
        yield [[$operator, '--email=user@example.test']];
        yield [['--internal', $operator, '--email=user@example.test', '--role=ROLE_SUPER_ADMIN']];
        yield [['--internal', $operator, '--email=user@example.test', '--private-secret-marker=token']];
        yield [['--internal', $operator, $operator, '--email=user@example.test']];
        yield [['--internal', $operator, '--email=user@example.test', '--email=other@example.test']];
        yield [['--internal', '--internal', $operator, '--email=user@example.test']];
        yield [['--internal', '--operator=', '--email=user@example.test']];
        yield [['--internal', '--operator=https://secret.example/private-token', '--email=user@example.test']];
        yield [['--internal', $operator, '--email=']];
        yield [['--internal', $operator, '--email=not-an-email-private-marker']];
        yield [['--internal', $operator, "--email=user@example.test\n"]];
        yield [['--internal', $operator, '--email= user@example.test']];
        yield [['--internal', $operator, '--email='.str_repeat('a', 255).'@example.test']];
        yield [['--internal', $operator, '--email=user@example.test', 'private-positional-secret']];
    }

    /**
     * Redacts bus exceptions and refuses a silently vetoed outer command
     */
    #[DataProvider('booleanCases')]
    public function testRuntimeFailure(bool $throw): void
    {
        $commands = $this->createMock(CommandBus::class);
        if ($throw) {
            $commands->method('execute')->willThrowException(new RuntimeException('SQL password=private-marker'));
        }
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturn(null);
        [$exit, $output] = $this->console($commands, $queries, [
            '--internal', '--operator='.UserId::generate()->toString(), '--email=user@example.test', '-vvv'
        ]);
        self::assertSame(1, $exit);
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $output);
    }

    /**
     * Refuses drift or an unsupported message before dispatching package behavior
     */
    #[DataProvider('booleanCases')]
    public function testHandlerRejection(bool $wrongMessage): void
    {
        $transaction = $this->createStub(InvitationTransaction::class);
        $transaction->method('commit')->willReturnCallback(static fn(callable $work) => $work());
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturn($this->plan(ManagedPolicyChangeAction::RECONCILE));
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('execute');
        $audit = $this->createMock(AuditEvidenceRepository::class);
        $audit->expects(self::never())->method('add');
        $input = new ConsoleInvitationInput(UserId::generate()->toString(), 'user@example.test');
        $command = new InviteOrdinaryUser($input, UserId::generate());
        if ($wrongMessage) {
            $command = new InvitePendingUser($input->actorId(), UserId::generate(), $input->email);
        }
        $this->expectException($wrongMessage ? InvalidArgumentException::class : LogicException::class);
        $handler = new InviteOrdinaryUserHandler($transaction, $queries, $commands, $audit);
        $handler->handle(CommandMessage::create($command));
    }

    /**
     * Refuses absent, transformed, active or elevated outcomes rather than claiming an ordinary invitation
     */
    #[DataProvider('wrongOutcomes')]
    public function testWrongOutcome(string $defect): void
    {
        $target = UserId::generate();
        $view = $defect === 'absent' ? null : $this->view(
            $defect === 'target' ? UserId::generate() : $target,
            $defect === 'email' ? 'other@example.test' : 'new.user@example.test',
            $defect === 'state' ? UserState::ACTIVE : UserState::PENDING_ACTIVATION,
            $defect === 'role' ? [] : [RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID)]
        );
        $this->expectException(LogicException::class);
        (new ConsoleInvitationInput(UserId::generate()->toString(), 'new.user@example.test'))
            ->requireOrdinaryOutcome($view, $target);
    }

    /**
     * Supplies incomplete or changed outcomes
     *
     * @return iterable<array{string}>
     */
    public static function wrongOutcomes(): iterable
    {
        foreach (['absent', 'target', 'email', 'state', 'role'] as $defect) {
            yield [$defect];
        }
    }

    /**
     * Keeps programmatic Symfony invocations inside the same validated safe failure contract
     */
    public function testProgrammaticInvocationSanitizesFailure(): void
    {
        $commands = $this->createStub(CommandBus::class);
        $commands->method('execute')->willThrowException(new RuntimeException('private-runtime-marker'));
        $tester = new \Symfony\Component\Console\Tester\CommandTester(new InviteUserCommand(
            $commands,
            $this->createStub(QueryBus::class)
        ));
        self::assertSame(1, $tester->execute([
            '--internal' => true,
            '--operator' => UserId::generate()->toString(),
            '--email'    => 'user@example.test'
        ]));
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $tester->getDisplay());
    }

    /**
     * Prevents interactive command suggestions from echoing unknown input before safe rendering
     */
    public function testUnknownCommandIsSafeNonInteractive(): void
    {
        $application = new SafeConsoleApplication();
        $application->setAutoExit(false);
        $application->addCommand(new InviteUserCommand(
            $this->createStub(CommandBus::class),
            $this->createStub(QueryBus::class)
        ));
        $output = new BufferedOutput();
        self::assertSame(2, $application->run(new ArgvInput(['console', 'user:invitz', '-vvv']), $output));
        self::assertSame('Invalid console input; use ./bin/console help user:invite.'."\n", $output->fetch());
    }

    /**
     * Redacts arbitrary runtime exceptions even when Symfony verbose rendering is requested
     */
    public function testRuntimeRenderingIsSafe(): void
    {
        $application = new SafeConsoleApplication();
        $application->setAutoExit(false);
        $command = new \Symfony\Component\Console\Command\Command('fault');
        $command->setCode(static function (): never {
            throw new RuntimeException('SQL password=private-marker https://private/credential');
        });
        $application->addCommand($command);
        $output = new BufferedOutput();
        self::assertSame(1, $application->run(new ArgvInput(['console', 'fault', '-vvv']), $output));
        self::assertSame(
            'Console failed or its outcome is uncertain; inspect trusted persisted state.'."\n",
            $output->fetch()
        );
    }

    /**
     * Refuses malformed serialized invitation messages
     */
    public function testInvalidMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InviteOrdinaryUser::fromArray(['email' => 'private-marker']);
    }

    /**
     * Produces opaque independent credentials without output
     */
    public function testRandomCredentials(): void
    {
        $generator = new RandomActivationCredentialGenerator();
        $first = $generator->generate()->toString();
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $first);
        self::assertNotSame($first, $generator->generate()->toString());
    }

    /**
     * Supplies both boolean branches
     *
     * @return iterable<array{bool}>
     */
    public static function booleanCases(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * Runs real Symfony parsing and the owned safe renderer
     *
     * @phpstan-param list<string> $arguments
     * @return array{int, string}
     */
    private function console(CommandBus $commands, QueryBus $queries, array $arguments): array
    {
        $application = new SafeConsoleApplication();
        $application->setAutoExit(false);
        $application->addCommand(new InviteUserCommand($commands, $queries));
        $output = new BufferedOutput();
        $exit = $application->run(new ArgvInput(['console', 'user:invite', ...$arguments]), $output);

        return [$exit, $output->fetch()];
    }

    /**
     * Creates a safe ordinary outcome independently of production orchestration
     *
     * @phpstan-param list<RoleId>|null $roles
     */
    private function view(
        UserId $target,
        string $email = 'new.user@example.test',
        UserState $state = UserState::PENDING_ACTIVATION,
        ?array $roles = null
    ): UserView {
        $now = new DateTimeImmutable('2026-10-14T12:00:00Z');

        return new UserView(
            $target,
            \Fight\Common\Domain\Value\Internet\EmailAddress::fromString($email),
            $state,
            $roles ?? [RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID)],
            $now,
            $now
        );
    }

    /**
     * Builds a complete package plan for the known desired policy
     */
    private function plan(ManagedPolicyChangeAction $action = ManagedPolicyChangeAction::UNCHANGED): ManagedPolicyPlan
    {
        $policy = InstallationManagedPolicy::create();

        return new ManagedPolicyPlan(
            array_map(
                static fn($definition) => new ManagedPermissionPlanItem($definition, $action),
                $policy->getPermissions()
            ),
            array_map(static fn($definition) => new ManagedRolePlanItem($definition, $action), $policy->getRoles())
        );
    }
}
