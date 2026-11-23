<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Adapter\Console\AddUserRoleCommand;
use App\Adapter\Console\InviteUserCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\CredentialDelivery\RandomActivationCredentialGenerator;
use App\Application\Authorization\CommandHandler\AddUserRoleHandler;
use App\Application\Authorization\CommandHandler\InviteOrdinaryUserHandler;
use App\Application\Authorization\IncompleteMembership;
use App\Application\Authorization\InstallationAssignmentPolicy;
use App\Domain\Authorization\Command\AddUserRole;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Authorization\MembershipEvidenceRepository;
use App\Domain\Authorization\MembershipProgress;
use App\Domain\Authorization\MembershipState;
use App\Domain\Authorization\OperatorMembershipEvidence;
use App\Domain\Authorization\TrustedOperator;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\Audit\SystemId;
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
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Value\Identifier\Uuid;
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
    /** @var list<OperatorMembershipEvidence> */
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
        $operation->execute($message);
        self::assertCount(2, $this->effects);
        self::assertInstanceOf(InvitePendingUser::class, $this->effects[0]);
        self::assertInstanceOf(AssignRoleToUser::class, $this->effects[1]);
        self::assertInstanceOf(SystemId::class, $this->effects[0]->getActorId());
        self::assertInstanceOf(SystemId::class, $this->effects[1]->getActorId());
        self::assertSame('console:'.$message->operator->operatorId, $this->evidence[0]->actorId);
        self::assertTrue($this->evidence[0]->userId->equals($message->userId));
        self::assertTrue($this->evidence[0]->roleId->equals($this->effects[1]->getRoleId()));
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
        $outcome = $this->failure($operation, $message);
        self::assertSame($message->userId, $outcome->knownUserId);
        self::assertSame('missing', $outcome->assignment);
        self::assertSame('unconfirmed', $outcome->audit);
        self::assertSame([], $this->evidence);
        $role = new AddUserRole(
            $message->operator,
            $message->userId,
            RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID),
            Uuid::comb()
        );
        $this->operation()->execute($role);
        self::assertSame($role->toArray(), AddUserRole::fromArray($role->toArray())->toArray());
        self::assertCount(1, $this->evidence);
    }

    /**
     * Reports unknown rather than success when authoritative outcome inspection fails
     */
    public function testInspectionFailure(): void
    {
        $inspection = $this->createStub(MembershipProgress::class);
        $inspection->method('inspect')->willThrowException(new RuntimeException('private-state-marker'));
        $operation = $this->operation(static function (): never {
            throw new RuntimeException('private-marker');
        }, inspection: $inspection);
        $outcome = $this->failure($operation, $this->invitation());
        self::assertNull($outcome->knownUserId);
        self::assertSame('unknown', $outcome->assignment);
        $target = UserId::generate();
        $this->user = $this->view($target);
        $outcome = $this->failure($operation, $this->roleAddition($target));
        self::assertNull($outcome->knownUserId);
        self::assertSame('unknown', $outcome->assignment);
    }

    /**
     * Confirms completion from current evidence if acknowledgement of the audit commit is lost
     */
    public function testAuditAcknowledgementLost(): void
    {
        $bus = $this->operation(loseAuditAcknowledgement: true);
        $invitation = $this->invitation();
        $bus->execute($invitation);
        $bus->execute($this->roleAddition($invitation->userId));
        self::assertCount(2, $this->evidence);
    }

    /**
     * Rejects a different command payload without applying invitation or membership effects
     */
    public function testHandlersRejectOtherPayloads(): void
    {
        $policy = new InstallationAssignmentPolicy(
            $this->createStub(QueryBus::class),
            new \Tests\Support\InstallationCatalogFixture()
        );
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('execute');
        $inspection = $this->inspection();
        $transactions = $this->createStub(TransactionalUnitOfWork::class);
        $audit = $this->createStub(MembershipEvidenceRepository::class);
        $invite = new InviteOrdinaryUserHandler($commands, $policy, $inspection, $transactions, $audit);
        $addRole = new AddUserRoleHandler($commands, $policy, $inspection, $transactions, $audit);
        $mismatches = [[$invite, $this->roleAddition(UserId::generate())], [$addRole, $this->invitation()]];
        foreach ($mismatches as [$handler, $message]) {
            try {
                $handler->handle(CommandMessage::create($message));
                self::fail('Mismatched command payload must be refused.');
            } catch (InvalidArgumentException $error) {
                self::assertStringStartsWith('Unexpected ', $error->getMessage());
            }
        }
    }

    /**
     * Rejects attribution whose UUID differs from the admitted operator
     */
    public function testMismatchedOperatorAttribution(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TrustedOperator(UserId::generate(), 'console:'.UserId::generate()->toString());
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
                    RoleName::fromString('ROLE_CUSTOM'), false, [],
                    \Fight\AccessControl\Domain\AccessControl\Role\RoleRevision::initial()
                ),
                $defect === 'no-role-view' && $query instanceof GetRoleById => null,
                $defect === 'protected' && $query instanceof GetPermissionById => new PermissionView(
                    $query->getPermissionId(),
                    PermissionName::fromString('READ_USERS'), PermissionTier::SUPER_ADMIN_ONLY, true
                ),
                $defect === 'permission' && $query instanceof GetPermissionById => null,
                $defect === 'catalog' && $query instanceof PreviewManagedPolicy => new ManagedPolicyPlan([], []),
                $defect === 'no-preview' && $query instanceof PreviewManagedPolicy => null,
                default => $normal
            };
        });
        $this->failure($operation, $this->roleAddition($target));
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
        foreach (['target', 'role', 'no-role-view', 'protected', 'permission', 'catalog', 'no-preview'] as $defect) {
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
        $this->expectException(LogicException::class);
        InstallationManagedPolicy::requireAdditiveRole($id, new RoleView(
            $kind === 'mismatch' ? RoleId::generate() : $id,
            RoleName::fromString($kind === 'alias' ? 'ROLE_SUPER_ADMIN' : 'ROLE_RENAMED'),
            $kind !== 'alias',
            [],
            \Fight\AccessControl\Domain\AccessControl\Role\RoleRevision::initial()
        ));
    }

    /**
     * Supplies stable identity and package-semantic elevation cases
     *
     * @return iterable<array{string}>
     */
    public static function unsafeRoles(): iterable
    {
        foreach (['stable', 'alias', 'managed', 'mismatch'] as $kind) {
            yield [$kind];
        }
    }

    /**
     * Refuses absent, transformed, active and elevated invitation outcomes
     */
    #[DataProvider('wrongOutcomes')]
    public function testWrongOutcome(string $defect): void
    {
        $message = $this->invitation();
        $view = $defect === 'absent' ? null : $this->view(
            $defect === 'target' ? UserId::generate() : $message->userId,
            $defect === 'email' ? 'other@example.test' : 'new.user@example.test',
            $defect === 'state' ? UserState::ACTIVE : UserState::PENDING_ACTIVATION,
            $defect === 'role' ? [] : [RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID)]
        );
        $reads = 0;
        $bus = $this->operation(null, static function ($query, $normal) use ($view, &$reads) {
            return $query instanceof GetUserById && ++$reads === 2 ? $view : $normal;
        });
        $this->failure($bus, $message);
        self::assertCount(2, $this->effects);
        self::assertSame([], $this->evidence);
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
     * Withholds confirmation evidence when the target disappears or existing membership is lost
     */
    #[DataProvider('incompleteMembership')]
    public function testIncompleteAdditiveOutcome(bool $absent): void
    {
        $target = UserId::generate();
        $this->user = $this->view($target, roles: [RoleId::generate()]);
        $reads = 0;
        $operation = $this->operation(null, static function ($query, $normal) use ($absent, &$reads) {
            if ($query instanceof GetUserById && ++$reads >= 2 && $absent) {
                return null;
            }

            return $normal;
        });
        $outcome = $this->failure($operation, $this->roleAddition($target));
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
        $invite = new \Symfony\Component\Console\Tester\CommandTester(
            new InviteUserCommand($operation, $this->inspection())
        );
        self::assertSame(1, $invite->execute([
            '--internal' => true,
            '--operator' => $operator,
            '--email'    => 'user@example.test'
        ]));
        self::assertSame(InviteUserCommand::FAILURE_MESSAGE."\n", $invite->getDisplay());
        $this->user = $this->view($user);
        $correction = new \Symfony\Component\Console\Tester\CommandTester(
            new AddUserRoleCommand($operation, $this->inspection())
        );
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
     * Reports success only after a void dispatch is confirmed by a current read
     */
    #[DataProvider('consoleReadOutcomes')]
    public function testConsoleDispatchOutcome(string $name, string $outcome): void
    {
        $target = UserId::generate();
        $commands = $this->createStub(CommandBus::class);
        $inspection = $this->createStub(MembershipProgress::class);
        $inspection->method('inspect')->willReturnCallback(static function (OperatorMembershipEvidence $evidence) use (
            $outcome
        ): MembershipState {
            if ($outcome === 'unavailable') {
                throw new RuntimeException('private-inspection-diagnostic');
            }

            if ($outcome === 'confirmed') {
                return new MembershipState($evidence->userId, 'confirmed', 'confirmed');
            }

            return new MembershipState($evidence->userId, 'missing', 'unconfirmed');
        });
        $application = new SafeConsoleApplication();
        $application->setAutoExit(false);
        $application->addCommand(new InviteUserCommand($commands, $inspection));
        $application->addCommand(new AddUserRoleCommand($commands, $inspection));
        $requested = $name === 'user:invite' ? ['--email=user@example.test'] : [
            '--user='.$target->toString(), '--role='.InstallationManagedPolicy::USER_ROLE_ID
        ];
        $input = new ArgvInput(['console', $name, '--internal', '--operator='.UserId::generate(), ...$requested]);
        $output = new BufferedOutput();
        self::assertSame($outcome === 'confirmed' ? 0 : 1, $application->run($input, $output));
        $text = $output->fetch();
        if ($outcome === 'unavailable') {
            $diagnostic = match ($name) {
                'user:invite' => InviteUserCommand::FAILURE_MESSAGE,
                default       => AddUserRoleCommand::FAILURE_MESSAGE
            };
            self::assertSame($diagnostic."\n", $text);
        } else {
            $status = $outcome === 'confirmed' ? 'role_present' : 'partial_or_uncertain';
            self::assertSame($status, json_decode($text, true, flags: JSON_THROW_ON_ERROR)['status']);
        }
    }

    /**
     * Supplies confirmation, silent veto and unavailable inspection cases
     *
     * @return iterable<array{string, string}>
     */
    public static function consoleReadOutcomes(): iterable
    {
        yield ['user:role:add', 'confirmed'];
        yield ['user:role:add', 'missing'];
        yield ['user:role:add', 'unavailable'];
        yield ['user:invite', 'missing'];
        yield ['user:invite', 'unavailable'];
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
     * Observes package effects and owned policy without reimplementing upstream business transactions
     *
     * @phpstan-param (callable(Command): void)|null $effect
     * @phpstan-param (callable(mixed, mixed): mixed)|null $queryResult
     */
    private function operation(
        ?callable $effect = null,
        ?callable $queryResult = null,
        ?MembershipProgress $inspection = null,
        bool $loseAuditAcknowledgement = false
    ): CommandBus {
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(function ($query) use ($queryResult) {
            $normal = match (true) {
                $query instanceof GetUserById => $this->user,
                $query instanceof GetRoleById => new RoleView(
                    $query->getRoleId(),
                    RoleName::fromString('ROLE_USER'), true, [PermissionId::generate()],
                    \Fight\AccessControl\Domain\AccessControl\Role\RoleRevision::initial()
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
        $transactions->method('commitTransactional')->willReturnCallback(
            static function (callable $work) use ($loseAuditAcknowledgement): mixed {
                $result = $work();
                if ($loseAuditAcknowledgement) {
                    throw new RuntimeException('Audit acknowledgement lost.');
                }

                return $result;
            }
        );
        $audit = $this->createStub(MembershipEvidenceRepository::class);
        $audit->method('add')->willReturnCallback(function (OperatorMembershipEvidence $evidence): void {
            $this->evidence[] = $evidence;
        });
        $policy = new InstallationAssignmentPolicy($queries, new \Tests\Support\InstallationCatalogFixture());
        $router = $this->createStub(SynchronousCommandBus::class);
        $bus = new CommandPipeline($router);
        $inspection ??= $this->inspection();
        $invite = new InviteOrdinaryUserHandler($bus, $policy, $inspection, $transactions, $audit);
        $addRole = new AddUserRoleHandler($bus, $policy, $inspection, $transactions, $audit);
        $router->method('dispatch')->willReturnCallback(function (CommandMessage $message) use (
            $effect,
            $invite,
            $addRole
        ): void {
            $command = $message->payload();
            if ($command instanceof InviteOrdinaryUser) {
                $invite->handle($message);

                return;
            }
            if ($command instanceof AddUserRole) {
                $addRole->handle($message);

                return;
            }
            assert($command instanceof Command);
            if ($effect !== null) {
                $effect($command);
            }
            $this->effects[] = $command;
            if ($command instanceof InvitePendingUser) {
                $this->user = $this->view($command->getUserId(), $command->getEmail()->toString(), roles: []);
            } elseif ($command instanceof AssignRoleToUser) {
                $this->user = $this->view($command->getTargetUserId(), $this->user->getEmail()->toString());
            }
        });

        return $bus;
    }

    /**
     * Supplies retained facts for unit tests; PostgreSQL integration proves the snapshot semantics
     */
    private function inspection(): MembershipProgress
    {
        $inspection = $this->createStub(MembershipProgress::class);
        $inspection->method('inspect')->willReturnCallback(function (
            OperatorMembershipEvidence $fact
        ): MembershipState {
            if ($this->user === null || !$this->user->getUserId()->equals($fact->userId)) {
                return new MembershipState();
            }
            $confirmed = false;
            foreach ($this->evidence as $record) {
                $confirmed = $confirmed || $record->invocationId->toString() === $fact->invocationId->toString();
            }

            return new MembershipState(
                $fact->userId,
                in_array($fact->roleId, $this->user->getRoleIds()) ? 'confirmed' : 'missing',
                $confirmed ? 'confirmed' : 'unconfirmed'
            );
        });

        return $inspection;
    }

    /**
     * Requires typed partial facts instead of normal command completion
     */
    private function failure(CommandBus $bus, Command $command): MembershipState
    {
        try {
            $bus->execute($command);
        } catch (IncompleteMembership $failure) {
            return $failure->state;
        }

        self::fail('Expected incomplete membership.');
    }

    /**
     * Runs real Symfony parsing and safe rendering
     *
     * @phpstan-param list<string> $arguments
     * @return array{int, string}
     */
    private function console(CommandBus $operation, string $name, array $arguments): array
    {
        $application = new SafeConsoleApplication();
        $application->setAutoExit(false);
        $inspection = $this->inspection();
        $application->addCommand(new InviteUserCommand($operation, $inspection));
        $application->addCommand(new AddUserRoleCommand($operation, $inspection));
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
            $this->operator(),
            EmailAddress::fromString('new.user@example.test'),
            UserId::generate(),
            Uuid::comb()
        );
    }

    /**
     * Supplies a role correction for the existing user
     */
    private function roleAddition(UserId $userId): AddUserRole
    {
        return new AddUserRole(
            $this->operator(),
            $userId,
            RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID),
            Uuid::comb()
        );
    }

    /**
     * Supplies trusted operator attribution distinct from the target
     */
    private function operator(): TrustedOperator
    {
        $id = UserId::generate();

        return new TrustedOperator($id, 'console:'.$id->toString());
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
        $policy = (new \Tests\Support\InstallationCatalogFixture())->load();

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
