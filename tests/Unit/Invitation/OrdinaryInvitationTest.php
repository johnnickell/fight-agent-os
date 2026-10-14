<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Adapter\Console\AddUserRoleCommand;
use App\Adapter\Console\InviteUserCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\CredentialDelivery\RandomActivationCredentialGenerator;
use App\Application\Invitation\CommandHandler\AddUserRoleHandler;
use App\Application\Invitation\CommandHandler\InviteOrdinaryUserHandler;
use App\Application\Invitation\ConsoleAssignmentPolicy;
use App\Application\Invitation\ConsoleCommandIntent;
use App\Application\Invitation\ConsoleUserCommandGuard;
use App\Application\Invitation\ConsoleUserOperation;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\Command\AddUserRole;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use App\Domain\Invitation\ConsoleInvitationInput;
use App\Domain\Invitation\ConsoleRoleInput;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPermissionPlanItem;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyChangeAction;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyPlan;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedRolePlanItem;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\GetPermissionById;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\PermissionView;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
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

/**
 * Protects owned sequencing, inspected uncertainty, terminal policy and secret-safe transport
 */
final class OrdinaryInvitationTest extends TestCase
{
    private ?UserView $user = null;
    /** @var list<AuditEvidence> */
    private array $evidence = [];
    /** @var list<Command> */
    private array $effects = [];

    /**
     * Sequences ordinary invitation and explicit operator evidence through normal dispatch
     */
    public function testInvitation(): void
    {
        $operation = $this->operation();
        $message = $this->invitation();
        $outcome = $operation->run($message);
        self::assertTrue($outcome->success);
        self::assertSame('confirmed', $outcome->assignment);
        self::assertSame('confirmed', $outcome->audit);
        self::assertCount(2, $this->effects);
        self::assertInstanceOf(InvitePendingUser::class, $this->effects[0]);
        self::assertInstanceOf(AssignRoleToUser::class, $this->effects[1]);
        self::assertTrue($message->input->operatorId->equals($this->effects[1]->getActorId()));
        self::assertSame('console:'.$message->input->operatorId, $this->evidence[0]->actorId());
        self::assertTrue($this->evidence[0]->subjectId()->equals($message->userId));
        self::assertSame('membership_confirmed', $this->evidence[0]->context()['result']);
        self::assertSame($message->toArray(), InviteOrdinaryUser::fromArray($message->toArray())->toArray());
    }

    /**
     * Presents canonical success and permits repeated successful shared invocations
     */
    public function testConsoleSuccess(): void
    {
        $operation = $this->operation();
        foreach (['New.User@EXAMPLE.test', 'other@example.test'] as $email) {
            [$exit, $text] = $this->console($operation, 'user:invite', [
                '--internal', '--operator='.UserId::generate(), '--email='.$email
            ]);
            self::assertSame(0, $exit);
            $result = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(strtolower($email), $result['email']);
            self::assertSame('invited', $result['status']);
        }
        self::assertCount(2, $this->evidence);
    }

    /**
     * Rejects malformed input without dispatch or unsafe Symfony diagnostics
     *
     * @phpstan-param list<string> $arguments
     */
    #[DataProvider('invalidArguments')]
    public function testInvalidArguments(string $name, array $arguments): void
    {
        [$exit, $text] = $this->console($this->operation(), $name, [...$arguments, '-vvv']);
        self::assertSame(2, $exit);
        self::assertContains($text, [
            InviteUserCommand::USAGE."\n", AddUserRoleCommand::USAGE."\n",
            'Invalid console input; use ./bin/console help user:invite.'."\n"
        ]);
        self::assertSame([], $this->effects);
        self::assertSame([], $this->evidence);
    }

    /**
     * Supplies invalid, repeated and secret-bearing transport selectors
     *
     * @return iterable<array{string, list<string>}>
     */
    public static function invalidArguments(): iterable
    {
        $operator = '--operator='.UserId::generate();
        foreach (
            [
            [], [$operator, '--email=user@example.test'],
            ['--internal', $operator, '--email=user@example.test', '--role=ROLE_SUPER_ADMIN'],
            ['--internal', $operator, '--email=user@example.test', '--private-secret-marker=token'],
            ['--internal', $operator, $operator, '--email=user@example.test'],
            ['--internal', $operator, '--email=user@example.test', '--email=other@example.test'],
            ['--internal', '--internal', $operator, '--email=user@example.test'],
            ['--internal', '--operator=', '--email=user@example.test'],
            ['--internal', '--operator=https://secret.example/private-token', '--email=user@example.test'],
            ['--internal', $operator, '--email='], ['--internal', $operator, '--email=not-an-email'],
            ['--internal', $operator, "--email=user@example.test\n"],
            ['--internal', $operator, '--email= user@example.test'],
            ['--internal', $operator, '--email='.str_repeat('a', 255).'@example.test'],
            ['--internal', $operator, '--email=user@example.test', 'private-positional-secret']
            ] as $arguments
        ) {
            yield ['user:invite', $arguments];
        }
        $base = ['--internal', $operator, '--user='.UserId::generate(), '--role='.RoleId::generate()];
        foreach (
            [[], array_slice($base, 1), [...$base, $operator], [...$base, '--user=private-marker'],
            [...$base, '--role=https://private/credential'], [...$base, '--internal'],
            ['--internal', $operator, '--user=bad', '--role='.RoleId::generate()]] as $arguments
        ) {
            yield ['user:role:add', $arguments];
        }
    }

    /**
     * Inspects retained invitation state after second-step failure and supports a fresh corrective invocation
     */
    public function testPartialFailureAndCorrection(): void
    {
        $operation = $this->operation(static function (Command $command): void {
            if ($command instanceof AssignRoleToUser) {
                throw new RuntimeException('SQL password=private-marker');
            }
        });
        $message = $this->invitation();
        $outcome = $operation->run($message);
        self::assertFalse($outcome->success);
        self::assertSame($message->userId, $outcome->knownUserId);
        self::assertSame('missing', $outcome->assignment);
        self::assertSame('unconfirmed', $outcome->audit);
        self::assertSame([], $this->evidence);
        $role = new AddUserRole(new ConsoleRoleInput(
            $message->input->operatorId->toString(),
            $message->userId->toString(),
            InstallationManagedPolicy::USER_ROLE_ID
        ));
        self::assertTrue($this->operation()->run($role)->success);
        self::assertSame($role->toArray(), AddUserRole::fromArray($role->toArray())->toArray());
        self::assertCount(1, $this->evidence);
    }

    /**
     * Reports unknown rather than success when authoritative outcome inspection fails
     */
    public function testInspectionFailure(): void
    {
        $queries = 0;
        $operation = $this->operation(
            static function (): never {
                throw new RuntimeException('private-marker');
            },
            static function ($query, $normal) use (&$queries) {
                if ($query instanceof GetUserById) {
                    $queries++;
                    throw new RuntimeException('private-state-marker');
                }

                return $normal;
            }
        );
        $outcome = $operation->run($this->invitation());
        self::assertFalse($outcome->success);
        self::assertNull($outcome->knownUserId);
        self::assertSame('unknown', $outcome->assignment);
        self::assertSame(1, $queries);
    }

    /**
     * Rejects changed safe query references and protected permissions before assignment
     */
    #[DataProvider('unsafeQueries')]
    public function testUnsafeQueryState(string $defect): void
    {
        $target = UserId::generate();
        $this->user = $this->view($target);
        $operation = $this->operation(null, function ($query, $normal) use ($defect) {
            return match (true) {
                $defect === 'target' && $query instanceof GetUserById => $this->view(UserId::generate()),
                $defect === 'role' && $query instanceof GetRoleById => new RoleView(
                    RoleId::generate(),
                    RoleName::fromString('ROLE_CUSTOM'), false, []
                ),
                $defect === 'protected' && $query instanceof GetPermissionById => new PermissionView(
                    $query->getPermissionId(),
                    PermissionName::fromString('READ_USERS'), PermissionTier::SUPER_ADMIN_ONLY, true
                ),
                $defect === 'permission' && $query instanceof GetPermissionById => null,
                $defect === 'catalog' && $query instanceof PreviewManagedPolicy => new ManagedPolicyPlan([], []),
                default => $normal
            };
        });
        $outcome = $operation->run(new AddUserRole(new ConsoleRoleInput(
            UserId::generate()->toString(),
            $target->toString(),
            InstallationManagedPolicy::USER_ROLE_ID
        )));
        self::assertFalse($outcome->success);
        self::assertSame([], $this->effects);
        self::assertSame([], $this->evidence);
    }

    /**
     * Supplies authoritative reference and tier failures
     *
     * @return iterable<array{string}>
     */
    public static function unsafeQueries(): iterable
    {
        foreach (['target', 'role', 'protected', 'permission', 'catalog'] as $defect) {
            yield [$defect];
        }
    }

    /**
     * Rejects renamed stable elevation, semantic aliases and unknown managed catalog additions
     */
    #[DataProvider('unsafeRoles')]
    public function testRoleMeaning(string $kind): void
    {
        $id = RoleId::generate();
        if ($kind === 'stable') {
            $id = RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID);
        }
        $input = new ConsoleRoleInput(UserId::generate()->toString(), UserId::generate()->toString(), $id->toString());
        $this->expectException(LogicException::class);
        $input->requireEligibleRole($kind === 'absent' ? null : new RoleView(
            $id,
            RoleName::fromString($kind === 'alias' ? 'ROLE_SUPER_ADMIN' : 'ROLE_RENAMED'),
            $kind !== 'alias',
            []
        ));
    }

    /**
     * Supplies stable identity and package-semantic elevation cases
     *
     * @return iterable<array{string}>
     */
    public static function unsafeRoles(): iterable
    {
        foreach (['stable', 'alias', 'managed', 'absent'] as $kind) {
            yield [$kind];
        }
    }

    /**
     * Refuses absent, transformed, active and elevated invitation outcomes
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
     * Rejects unsupported handler messages before any state or evidence mutation
     */
    public function testUnexpectedHandlerMessages(): void
    {
        $operation = $this->operation();
        foreach ([new AddUserRoleHandler($operation), new InviteOrdinaryUserHandler($operation)] as $handler) {
            try {
                $handler->handle(CommandMessage::create(new InvitePendingUser(
                    'console:'.UserId::generate(),
                    UserId::generate(),
                    EmailAddress::fromString('user@example.test')
                )));
                self::fail('Unexpected messages must fail closed.');
            } catch (InvalidArgumentException) {
                self::assertSame([], $this->effects);
                self::assertSame([], $this->evidence);
            }
        }
    }

    /**
     * Isolates unscoped and overlapping invocation state without poisoning the next operation
     */
    public function testInvocationScopeIsolation(): void
    {
        $operation = $this->operation();
        try {
            $operation->dispatch($this->invitation());
            self::fail('An invocation scope is mandatory.');
        } catch (LogicException) {
            self::assertSame([], $this->effects);
        }
        $overlap = null;
        $operation = $this->operation(function () use (&$operation, &$overlap): void {
            try {
                $operation->run($this->invitation());
            } catch (LogicException $error) {
                $overlap = $error;
            }
        });
        self::assertTrue($operation->run($this->invitation())->success);
        self::assertInstanceOf(LogicException::class, $overlap);
        self::assertCount(1, $this->evidence);
        self::assertTrue($operation->run($this->invitation())->success);
        self::assertCount(2, $this->evidence);
    }

    /**
     * Withholds confirmation evidence when the target disappears or existing membership is lost
     */
    #[DataProvider('incompleteMembership')]
    public function testIncompleteAdditiveOutcome(bool $absent): void
    {
        $target = UserId::generate();
        $this->user = $this->view($target, roles: [RoleId::generate()]);
        $reads = 0;
        $operation = $this->operation(null, static function ($query, $normal) use ($absent, &$reads) {
            if ($query instanceof GetUserById && ++$reads >= 3 && $absent) {
                return null;
            }

            return $normal;
        });
        $outcome = $operation->run(new AddUserRole(new ConsoleRoleInput(
            UserId::generate()->toString(),
            $target->toString(),
            InstallationManagedPolicy::USER_ROLE_ID
        )));
        self::assertFalse($outcome->success);
        self::assertSame('unconfirmed', $outcome->audit);
        self::assertSame([], $this->evidence);
    }

    /**
     * Supplies absent target and lost pre-existing membership
     *
     * @return iterable<array{bool}>
     */
    public static function incompleteMembership(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * Rejects malformed serialized messages without private input rendering
     */
    public function testInvalidMessages(): void
    {
        foreach ([InviteOrdinaryUser::class, AddUserRole::class] as $type) {
            try {
                $type::fromArray(['email' => 'private-marker']);
                self::fail('Malformed input must be refused.');
            } catch (InvalidArgumentException $error) {
                self::assertStringNotContainsString('private-marker', $error->getMessage());
            }
        }
    }

    /**
     * Prevents interactive suggestions and verbose rendering from echoing unknown input
     */
    public function testUnknownCommandAndRuntimeRendering(): void
    {
        [$exit, $text] = $this->console($this->operation(), 'user:invitz', ['-vvv']);
        self::assertSame(2, $exit);
        self::assertSame('Invalid console input; use ./bin/console help user:invite.'."\n", $text);
        $application = new SafeConsoleApplication();
        $application->setAutoExit(false);
        $command = new \Symfony\Component\Console\Command\Command('fault');
        $command->setCode(static function (): never {
            throw new RuntimeException('SQL password=private-marker');
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
     * Keeps programmatic Symfony invocations inside the same validated safe failure boundary
     */
    public function testProgrammaticInvocationStaysSafe(): void
    {
        $operator = UserId::generate()->toString();
        $user = UserId::generate();
        $operation = $this->operation(static function (): never {
            throw new RuntimeException('SQL password=private-programmatic-marker');
        });
        $invite = new \Symfony\Component\Console\Tester\CommandTester(new InviteUserCommand($operation));
        self::assertSame(1, $invite->execute([
            '--internal' => true,
            '--operator' => $operator,
            '--email'    => 'user@example.test'
        ]));
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $invite->getDisplay());
        $this->user = $this->view($user);
        $correction = new \Symfony\Component\Console\Tester\CommandTester(new AddUserRoleCommand($operation));
        self::assertSame(1, $correction->execute([
            '--internal' => true,
            '--operator' => $operator,
            '--user'     => $user->toString(),
            '--role'     => InstallationManagedPolicy::USER_ROLE_ID
        ]));
        self::assertSame('unconfirmed', json_decode($correction->getDisplay(), true)['audit']);
        self::assertStringNotContainsString('private-programmatic-marker', $correction->getDisplay());
        self::assertSame([], $this->effects);
        self::assertSame([], $this->evidence);
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
     * Creates a unit boundary with real owned handlers and filters, but no package business implementation
     *
     * @phpstan-param (callable(Command): void)|null $effect
     * @phpstan-param (callable(mixed, mixed): mixed)|null $queryResult
     */
    private function operation(?callable $effect = null, ?callable $queryResult = null): ConsoleUserOperation
    {
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(function ($query) use ($queryResult) {
            $normal = match (true) {
                $query instanceof GetUserById => $this->user,
                $query instanceof GetRoleById => new RoleView(
                    $query->getRoleId(),
                    RoleName::fromString('ROLE_USER'), true, [PermissionId::generate()]
                ),
                $query instanceof GetPermissionById => new PermissionView(
                    $query->getPermissionId(),
                    PermissionName::fromString('VIEW_DASHBOARD'), PermissionTier::ADMIN_SAFE, true
                ),
                default => $this->plan()
            };

            return $queryResult === null ? $normal : $queryResult($query, $normal);
        });
        $transactions = $this->createStub(TransactionalUnitOfWork::class);
        $transactions->method('commitTransactional')->willReturnCallback(static fn(callable $work) => $work());
        $audit = $this->createStub(AuditEvidenceRepository::class);
        $audit->method('add')->willReturnCallback(function (AuditEvidence $evidence): void {
            $this->evidence[] = $evidence;
        });
        $intent = new ConsoleCommandIntent();
        $policy = new ConsoleAssignmentPolicy($queries);
        $router = $this->createStub(SynchronousCommandBus::class);
        $bus = new CommandPipeline($router);
        $bus->addFilter(new ConsoleUserCommandGuard($intent, $policy));
        $operation = new ConsoleUserOperation($bus, $queries, $transactions, $audit, $intent, $policy);
        $router->method('dispatch')->willReturnCallback(function (
            CommandMessage $message
        ) use (
            $operation,
            $effect
        ): void {
            $command = $message->payload();
            assert($command instanceof Command);
            if ($command instanceof InviteOrdinaryUser) {
                (new InviteOrdinaryUserHandler($operation))->handle($message);
            } elseif ($command instanceof AddUserRole) {
                (new AddUserRoleHandler($operation))->handle($message);
            } else {
                if ($effect !== null) {
                    $effect($command);
                }
                $this->effects[] = $command;
                if ($command instanceof InvitePendingUser) {
                    $this->user = $this->view($command->getUserId(), $command->getEmail()->toString(), roles: []);
                } elseif ($command instanceof AssignRoleToUser) {
                    $this->user = $this->view($command->getTargetUserId(), $this->user->getEmail()->toString());
                }
            }
        });

        return $operation;
    }

    /**
     * Runs real Symfony parsing and safe rendering
     *
     * @phpstan-param list<string> $arguments
     * @return array{int, string}
     */
    private function console(ConsoleUserOperation $operation, string $name, array $arguments): array
    {
        $application = new SafeConsoleApplication();
        $application->setAutoExit(false);
        $application->addCommand(new InviteUserCommand($operation));
        $application->addCommand(new AddUserRoleCommand($operation));
        $output = new BufferedOutput();
        $exit = $application->run(new ArgvInput(['console', $name, ...$arguments]), $output);

        return [$exit, $output->fetch()];
    }

    /**
     * Supplies an intended invitation with library-generated identities
     */
    private function invitation(): InviteOrdinaryUser
    {
        return new InviteOrdinaryUser(
            new ConsoleInvitationInput(UserId::generate()->toString(), 'new.user@example.test'),
            UserId::generate()
        );
    }

    /**
     * Creates a safe outcome independently of owned orchestration
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
            EmailAddress::fromString($email),
            $state,
            $roles ?? [RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID)],
            $now,
            $now
        );
    }

    /**
     * Builds a complete known-policy plan rather than an empty no-op preview
     */
    private function plan(): ManagedPolicyPlan
    {
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
    }
}
