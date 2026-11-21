<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\Repository\PostgresAuditEvidenceRepository;
use App\Domain\Authorization\OperatorMembershipEvidence;
use App\Domain\Authorization\TrustedOperator;
use App\Domain\Bootstrap\BootstrapEvidence;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use App\Domain\Invitation\InvitationMode;
use App\Domain\Invitation\InvitationRoleEvidence;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentId;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditAction;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditAgentPermissionOutcome;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditRoleRevisions;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditSubject;
use Fight\AccessControl\Domain\AccessControl\Audit\SystemId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRevision;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Protects the audit record sent to storage without substituting for PostgreSQL transaction checks
 */
final class AuditRecordProjectionTest extends TestCase
{
    /**
     * Preserves the typed actor and Role subject and stores only the admitted facts
     */
    public function testRoleRecordPreservesTypedEvidence(): void
    {
        $actor = SystemId::generate()->toString();
        $role = RoleId::generate();
        $revision = RoleRevision::initial();
        $evidence = AuditEvidence::record(
            SystemId::generate(),
            new AuditSubject($role),
            AuditAction::ROLE_CREATED,
            new DateTimeImmutable('2026-10-01T12:00:00Z'),
            new AuditRoleRevisions(null, $revision),
            null
        );

        $this->assertAppend($evidence, $actor, 'role.created', 'role', $role->toString(), [
            'id'          => $evidence->getId()->toString(),
            'actor'       => ['kind' => 'system', 'id' => $actor],
            'subject'     => ['kind' => 'role', 'id' => $role->toString()],
            'action'      => 'role.created',
            'occurred_at' => '2026-10-01T12:00:00.000000+00:00',
            'correlation' => null,
            'facts'       => ['before' => null, 'after' => $revision->toString()]
        ]);
    }

    /**
     * Keeps owned operator attribution and the existing narrow membership facts
     *
     * @phpstan-param array<string, mixed>|stdClass $facts
     */
    #[DataProvider('ownedEvidence')]
    public function testOwnedEvidencePreservesHistoryShape(
        BootstrapEvidence|OperatorMembershipEvidence|InvitationRoleEvidence $evidence,
        string $actor,
        string $action,
        UserId $target,
        array|stdClass $facts
    ): void {
        $this->assertAppend($evidence, $actor, $action, 'user', $target->toString(), $facts);
    }

    /**
     * Supplies the three distinct owned audit outcomes
     *
     * @return iterable<string, array{BootstrapEvidence|OperatorMembershipEvidence|InvitationRoleEvidence, string,
     *     string, UserId, array<string, mixed>|stdClass}>
     */
    public static function ownedEvidence(): iterable
    {
        $operator = UserId::generate();
        $id = $operator->toString();
        $target = UserId::generate();
        $role = RoleId::generate();
        $invocationId = Uuid::comb();
        yield 'bootstrap' => [
            BootstrapEvidence::completed(new BootstrapSuperAdmin(
                new TrustedOperator($operator, 'bootstrap:'.$id),
                EmailAddress::fromString('first@example.test'),
                $target
            )),
            'bootstrap:'.$id, 'user.bootstrap_completed', $target, new stdClass()
        ];
        yield 'console' => [
            OperatorMembershipEvidence::membershipConfirmed(
                new TrustedOperator($operator, 'console:'.$id),
                $target,
                $role,
                $invocationId
            ),
            'console:'.$id, 'user.console_role_assigned', $target,
            [
                'role_id'       => $role->toString(),
                'result'        => 'membership_confirmed',
                'invocation_id' => $invocationId->toString()
            ]
        ];
        $mode = InvitationMode::ORDINARY;
        yield 'invitation' => [
            InvitationRoleEvidence::confirmed($operator, $target, $mode),
            $id, 'user.invitation_role_confirmed', $target,
            ['role_id' => $mode->roleId()->toString(), 'result' => 'membership_confirmed']
        ];
    }

    /**
     * Refuses a valid typed record over the storage byte limit before attempting an append
     */
    public function testOversizeTypedEvidenceNeverReachesStorage(): void
    {
        $permissions = [];
        for ($index = 0; $index < 110; ++$index) {
            $permissions[] = PermissionId::generate();
        }
        $evidence = AuditEvidence::record(
            UserId::generate(),
            new AuditSubject(AgentId::generate()),
            AuditAction::AGENT_PERMISSIONS_REPLACED,
            new DateTimeImmutable('2026-10-01T12:00:00Z'),
            new AuditAgentPermissionOutcome(1, 2, $permissions, []),
            null
        );
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::never())->method('insert');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audit evidence exceeds its storage bound.');
        new PostgresAuditEvidenceRepository($connection)->add($evidence);
    }

    /**
     * Asserts the append's public storage arguments, not SQL or transaction effects
     *
     * @phpstan-param array<string, mixed>|stdClass $facts
     */
    private function assertAppend(
        AuditEvidence|BootstrapEvidence|OperatorMembershipEvidence|InvitationRoleEvidence $evidence,
        string $actor,
        string $action,
        string $subjectType,
        string $subjectId,
        array|stdClass $facts
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('insert')->with('audit_evidence', [
            'actor_id'     => $actor,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'context'      => json_encode($facts, JSON_THROW_ON_ERROR)
        ]);

        new PostgresAuditEvidenceRepository($connection)->add($evidence);
    }
}
