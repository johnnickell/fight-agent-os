<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\Authorization\PostgresMembershipProgress;
use App\Adapter\Persistence\Bootstrap\PostgresBootstrapProgress;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Authorization\OperatorMembershipEvidence;
use App\Domain\Authorization\TrustedOperator;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Closure;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects operator recovery from inferring progress not established by retained evidence
 */
final class OperatorProgressTest extends TestCase
{
    private const string EMAIL = 'participant@example.test';

    /**
     * Distinguishes absent or wrong targets from an invitation for this bootstrap
     *
     * @param array{email: string, state: string}|false $row
     * @param array{string, string} $invitationAndAssignment
     */
    #[DataProvider('unconfirmedTargets')]
    public function testBootstrapDoesNotAttributeAnotherTarget(array|false $row, array $invitationAndAssignment): void
    {
        $command = $this->bootstrap();
        $connection = $this->snapshot($command->userId, $row);
        $connection->expects(self::never())->method('fetchFirstColumn');
        $connection->expects(self::never())->method('fetchOne');

        $state = (new PostgresBootstrapProgress($connection))->inspect($command);

        self::assertSame('unknown', $state->admission);
        self::assertSame($invitationAndAssignment, [$state->invitation, $state->assignment]);
        self::assertFalse($state->isComplete());
    }

    /**
     * Supplies stored states that do not establish the target pending invitation
     *
     * @return iterable<string, array{array{email: string, state: string}|false, array{string, string}}>
     */
    public static function unconfirmedTargets(): iterable
    {
        yield 'target absent' => [false, ['missing', 'missing']];
        yield 'email differs' => [
            ['email' => 'other@example.test', 'state' => 'pending_activation'], ['unknown', 'unknown']
        ];
        yield 'already active' => [['email' => self::EMAIL, 'state' => 'active'], ['unknown', 'unknown']];
    }

    /**
     * Requires the exact super-admin assignment and the operator's completion audit
     */
    #[DataProvider('bootstrapEvidence')]
    public function testBootstrapRequiresExactRoleAndAudit(bool $extraRole, bool $completed): void
    {
        $command = $this->bootstrap();
        $id = $command->userId->toString();
        $connection = $this->snapshot($command->userId, ['email' => self::EMAIL, 'state' => 'pending_activation']);
        $roles = [InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID];
        if ($extraRole) {
            $roles[] = RoleId::generate()->toString();
        }
        $connection->expects(self::once())->method('fetchFirstColumn')
            ->with(self::anything(), [$id])->willReturn($roles);
        $connection->expects(self::once())->method('fetchOne')->with(
            self::anything(),
            [$command->operator->auditActorId, $id, 'user.bootstrap_completed']
        )->willReturn($completed ? 1 : false);

        $state = (new PostgresBootstrapProgress($connection))->inspect($command);

        self::assertSame('confirmed', $state->invitation);
        self::assertSame($extraRole ? 'missing' : 'confirmed', $state->assignment);
        self::assertSame($completed ? 'admitted' : 'unknown', $state->admission);
        self::assertSame($completed ? 'confirmed' : 'unconfirmed', $state->audit);
        self::assertSame(!$extraRole && $completed, $state->isComplete());
    }

    /**
     * Supplies retained bootstrap evidence and its independent failure modes
     *
     * @return iterable<string, array{bool, bool}>
     */
    public static function bootstrapEvidence(): iterable
    {
        yield 'both requirements retained' => [false, true];
        yield 'unexpected extra role' => [true, true];
        yield 'completion audit missing' => [false, false];
    }

    /**
     * Does not report known membership for a missing or different pending target
     *
     * @param array{email: string, state: string}|false $row
     */
    #[DataProvider('unconfirmedMembershipTargets')]
    public function testMembershipRejectsUnconfirmedTarget(array|false $row): void
    {
        $evidence = $this->membership();
        $connection = $this->snapshot($evidence->userId, $row);
        $connection->expects(self::never())->method('fetchFirstColumn');
        $connection->expects(self::never())->method('fetchOne');

        $state = (new PostgresMembershipProgress($connection))->inspect(
            $evidence,
            EmailAddress::fromString(self::EMAIL)
        );

        self::assertNull($state->knownUserId);
        self::assertFalse($state->isComplete());
    }

    /**
     * Supplies stored states that cannot establish pending membership
     *
     * @return iterable<string, array{array{email: string, state: string}|false}>
     */
    public static function unconfirmedMembershipTargets(): iterable
    {
        foreach (self::unconfirmedTargets() as $label => [$row]) {
            yield $label => [$row];
        }
    }

    /**
     * Distinguishes a retained additive assignment from the stricter pending-target assignment
     */
    #[DataProvider('membershipEvidence')]
    public function testMembershipRequiresSameInvocation(
        bool $pending,
        bool $hasRole,
        bool $hasAudit,
        string $assignment,
        bool $complete
    ): void {
        $evidence = $this->membership();
        $user = $evidence->userId->toString();
        $role = $evidence->roleId->toString();
        $connection = $this->snapshot($evidence->userId, ['email' => self::EMAIL, 'state' => 'pending_activation']);
        $roles = [RoleId::generate()->toString()];
        if ($hasRole) {
            $roles[] = $role;
        }
        $connection->expects(self::once())->method('fetchFirstColumn')
            ->with(self::anything(), [$user])->willReturn($roles);
        $connection->expects(self::once())->method('fetchOne')->with(
            self::anything(),
            [$evidence->actorId, $user, $role, $evidence->invocationId->toString()]
        )->willReturn($hasAudit ? 1 : false);

        $state = (new PostgresMembershipProgress($connection))->inspect(
            $evidence,
            $pending ? EmailAddress::fromString(self::EMAIL) : null
        );

        self::assertSame($evidence->userId, $state->knownUserId);
        self::assertSame($assignment, $state->assignment);
        self::assertSame($hasAudit ? 'confirmed' : 'unconfirmed', $state->audit);
        self::assertSame($complete, $state->isComplete());
    }

    /**
     * Supplies retained memberships and invocation evidence
     *
     * @return iterable<string, array{bool, bool, bool, string, bool}>
     */
    public static function membershipEvidence(): iterable
    {
        yield 'additive target can have another role' => [false, true, true, 'confirmed', true];
        yield 'pending target must have exact role' => [true, true, true, 'unknown', false];
        yield 'audit alone does not prove assignment' => [false, false, true, 'missing', false];
        yield 'assignment alone does not prove invocation' => [false, true, false, 'confirmed', false];
    }

    /**
     * Runs the inspection callback without a database and checks the read-only snapshot boundary
     *
     * @param UserId $userId
     * @param array{email: string, state: string}|false $row
     */
    private function snapshot(UserId $userId, array|false $row): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional')
            ->willReturnCallback(static fn (Closure $inspect): mixed => $inspect());
        $connection->expects(self::once())->method('executeStatement')->with(
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY'
        );
        $connection->expects(self::once())->method('fetchAssociative')
            ->with(self::anything(), [$userId->toString()])->willReturn($row);

        return $connection;
    }

    /**
     * Builds an independently identified bootstrap request
     */
    private function bootstrap(): BootstrapSuperAdmin
    {
        return new BootstrapSuperAdmin($this->operator(), EmailAddress::fromString(self::EMAIL), UserId::generate());
    }

    /**
     * Builds an independently identified invocation
     */
    private function membership(): OperatorMembershipEvidence
    {
        return OperatorMembershipEvidence::membershipConfirmed(
            $this->operator(),
            UserId::generate(),
            RoleId::generate(),
            Uuid::comb()
        );
    }

    /**
     * Builds attributed installation operator data
     */
    private function operator(): TrustedOperator
    {
        $id = UserId::generate();

        return new TrustedOperator($id, 'test:'.$id->toString());
    }
}
