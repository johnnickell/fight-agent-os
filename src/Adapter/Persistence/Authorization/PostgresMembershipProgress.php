<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Authorization;

use App\Domain\Authorization\MembershipProgress;
use App\Domain\Authorization\MembershipState;
use App\Domain\Authorization\OperatorMembershipEvidence;
use Doctrine\DBAL\Connection;
use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Class PostgresMembershipProgress
 *
 * Inspects retained assignment and exact invocation evidence without the write connection
 */
final readonly class PostgresMembershipProgress implements MembershipProgress
{
    /**
     * Constructs PostgresMembershipProgress
     */
    public function __construct(private Connection $inspection)
    {
    }

    /**
     * @inheritDoc
     */
    public function inspect(OperatorMembershipEvidence $evidence, ?EmailAddress $pendingEmail = null): MembershipState
    {
        return $this->inspection->transactional(function () use ($evidence, $pendingEmail): MembershipState {
            $this->inspection->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');

            return $this->inspectSnapshot($evidence, $pendingEmail);
        });
    }

    /**
     * Reads target, current membership and evidence from one committed snapshot
     */
    private function inspectSnapshot(OperatorMembershipEvidence $evidence, ?EmailAddress $pendingEmail): MembershipState
    {
        $userId = $evidence->userId->toString();
        $roleId = $evidence->roleId->toString();
        $user = $this->inspection->fetchAssociative('SELECT email, state FROM users WHERE id = ?', [$userId]);
        if (
            $user === false || ($pendingEmail !== null && (
                $user['email'] !== $pendingEmail->toString() || $user['state'] !== 'pending_activation'
            ))
        ) {
            return new MembershipState();
        }
        $roles = $this->inspection->fetchFirstColumn('SELECT role_id FROM user_role_assignments WHERE user_id = ?', [
            $userId
        ]);
        $assignment = match (true) {
            !in_array($roleId, $roles, true) => 'missing',
            $pendingEmail !== null && $roles !== [$roleId] => 'unknown',
            default => 'confirmed'
        };
        $audit = $this->inspection->fetchOne(
            <<<'SQL'
SELECT 1 FROM audit_evidence
WHERE actor_id = ? AND subject_id = ? AND subject_type = 'user' AND action = 'user.console_role_assigned'
AND context->>'role_id' = ? AND context->>'invocation_id' = ?
LIMIT 1
SQL,
            [$evidence->actorId, $userId, $roleId, $evidence->invocationId->toString()]
        );

        return new MembershipState($evidence->userId, $assignment, $audit === false ? 'unconfirmed' : 'confirmed');
    }
}
