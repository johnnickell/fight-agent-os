<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Adapter\Console\OrdinaryInvitationConsole;
use App\Adapter\CredentialDelivery\RandomActivationCredentialGenerator;
use App\Application\Invitation\InvitationTransaction;
use App\Application\Invitation\InviteOrdinaryUser;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\ConsoleInvitationInput;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPermissionPlanItem;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyChangeAction;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyPlan;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedRolePlanItem;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Protects bounded input, least-authority orchestration and secret-free console outcomes
 */
final class OrdinaryInvitationTest extends TestCase
{
    /**
     * Uses the operator rather than target as actor and coordinates only the ordinary managed role
     */
    public function testInvitation(): void
    {
        $input = new ConsoleInvitationInput(UserId::generate()->toString(), 'New.User@EXAMPLE.test');
        $transaction = $this->createMock(InvitationTransaction::class);
        $transaction->expects(self::once())->method('commit')->willReturnCallback(static fn(callable $work) => $work());
        $queries = $this->createMock(QueryBus::class);
        $queries->method('fetch')->willReturn($this->plan());
        $invite = $this->createMock(CommandHandler::class);
        $assign = $this->createMock(CommandHandler::class);
        $audit = $this->createMock(AuditEvidenceRepository::class);
        $target = null;
        $invite->expects(self::once())->method('handle')->willReturnCallback(
            static function (CommandMessage $message) use ($input, &$target): void {
                $command = $message->payload();
                self::assertInstanceOf(InvitePendingUser::class, $command);
                $target = $command->getUserId();
                self::assertSame($input->actorId(), $command->getActorId());
                self::assertSame('new.user@example.test', $command->getEmail()->toString());
            }
        );
        $assign->expects(self::once())->method('handle')->willReturnCallback(
            static function (CommandMessage $message) use ($input, &$target): void {
                $command = $message->payload();
                self::assertInstanceOf(AssignRoleToUser::class, $command);
                self::assertSame($input->operatorId->toString(), $command->getActorId()->toString());
                self::assertSame($target, $command->getTargetUserId());
                self::assertSame(InstallationManagedPolicy::USER_ROLE_ID, $command->getRoleId()->toString());
            }
        );
        $audit->expects(self::once())->method('add')->with(self::callback(
            static fn(AuditEvidence $e): bool => $e->actorId() === $input->actorId()
                && $e->action() === 'user.console_role_assigned' && $e->context() === []
        ));
        $application = new InviteOrdinaryUser($transaction, $queries, $invite, $assign, $audit);
        $output = '';
        self::assertSame(0, (new OrdinaryInvitationConsole(static fn(): InviteOrdinaryUser => $application))->run(
            ['--internal', '--operator='.$input->operatorId->toString(), '--email=New.User@EXAMPLE.test'],
            static function (string $text) use (&$output): void {
                $output .= $text;
            },
            static function (): void {
                self::fail('Valid input should succeed.');
            }
        ));
        self::assertInstanceOf(UserId::class, $target);
        self::assertSame([
            'email' => 'new.user@example.test', 'user_id' => $target->toString(), 'status' => 'invited'
        ], json_decode($output, true, flags: JSON_THROW_ON_ERROR));
        self::assertNotSame($input->operatorId->toString(), $target->toString());
    }

    /**
     * Rejects malformed, repeated and authority-selecting arguments before resolving dependencies
     *
     * @phpstan-param list<string> $arguments
     */
    #[DataProvider('invalidArguments')]
    public function testInvalidArguments(array $arguments): void
    {
        $console = new OrdinaryInvitationConsole(static function (): never {
            self::fail('Invalid input must not resolve runtime dependencies.');
        });
        self::assertSame(2, $console->run(
            $arguments,
            static function (): void {
                self::fail('Invalid input must not emit success.');
            },
            static function (string $text): void {
                self::assertSame(OrdinaryInvitationConsole::USAGE, $text);
            }
        ));
    }

    /**
     * Supplies invalid input including secrets that must not be echoed
     *
     * @return iterable<array{list<string>}>
     */
    public static function invalidArguments(): iterable
    {
        $operator = '--operator='.UserId::generate()->toString();
        yield [[]];
        yield [[$operator, '--email=user@example.test']];
        yield [['--internal', $operator, '--email=user@example.test', '--role=ROLE_SUPER_ADMIN']];
        yield [['--internal', $operator, '--email=user@example.test', '--role-id=private-marker']];
        yield [['--internal', $operator, $operator]];
        yield [['--internal', '--email=user@example.test', $operator]];
        yield [['--internal', '--operator=', '--email=user@example.test']];
        yield [['--internal', '--operator=anonymous', '--email=user@example.test']];
        yield [['--internal', '--operator=https://secret.example/private-token', '--email=user@example.test']];
        yield [['--internal', $operator, '--email=']];
        yield [['--internal', $operator, '--email=not-an-email-private-marker']];
        yield [['--internal', $operator, '--email=user@example.test\nprivate-marker']];
        yield [['--internal', $operator, "--email=user@example.test\n"]];
        yield [['--internal', $operator, '--email= user@example.test']];
        yield [['--internal', $operator, '--email='.str_repeat('a', 255).'@example.test']];
    }

    /**
     * Redacts runtime and post-commit failures rather than reporting success
     */
    public function testRuntimeFailure(): void
    {
        $console = new OrdinaryInvitationConsole(static function (): never {
            throw new RuntimeException('SQL password=marker-secret https://private/credential ciphertext-marker');
        });
        self::assertSame(1, $console->run(
            ['--internal', '--operator='.UserId::generate()->toString(), '--email=user@example.test'],
            static function (): void {
                self::fail('Failure must not emit success.');
            },
            static function (string $text): void {
                self::assertSame(OrdinaryInvitationConsole::FAILURE, $text);
            }
        ));
    }

    /**
     * Refuses any unreconciled authority before calling package commands
     */
    public function testDriftFailsClosed(): void
    {
        $transaction = $this->createMock(InvitationTransaction::class);
        $transaction->method('commit')->willReturnCallback(static fn(callable $work) => $work());
        $queries = $this->createMock(QueryBus::class);
        $queries->method('fetch')->willReturn($this->plan(ManagedPolicyChangeAction::RECONCILE));
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::never())->method('handle');
        $audit = $this->createMock(AuditEvidenceRepository::class);
        $audit->expects(self::never())->method('add');
        $this->expectException(LogicException::class);
        (new InviteOrdinaryUser($transaction, $queries, $handler, $handler, $audit))->invite(
            new ConsoleInvitationInput(UserId::generate()->toString(), 'user@example.test')
        );
    }

    /**
     * Produces opaque fixed-size independent credentials without output
     */
    public function testRandomCredentials(): void
    {
        $generator = new RandomActivationCredentialGenerator();
        $first = $generator->generate()->toString();
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $first);
        self::assertNotSame($first, $generator->generate()->toString());
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
