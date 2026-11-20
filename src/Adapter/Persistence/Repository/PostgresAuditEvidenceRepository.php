<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Domain\Authorization\MembershipEvidenceRepository;
use App\Domain\Bootstrap\BootstrapEvidence;
use App\Domain\Invitation\ConsoleRoleEvidence;
use App\Domain\Invitation\InvitationRoleEvidence;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use InvalidArgumentException;
use LogicException;

/**
 * Class PostgresAuditEvidenceRepository
 *
 * Appends canonical evidence on the same connection as the audited mutation
 */
final readonly class PostgresAuditEvidenceRepository implements AuditEvidenceRepository, MembershipEvidenceRepository
{
    /**
     * Constructs PostgresAuditEvidenceRepository
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function add(AuditEvidence|BootstrapEvidence|ConsoleRoleEvidence|InvitationRoleEvidence $evidence): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Audit evidence requires an enclosing transaction.');
        }

        if ($evidence instanceof AuditEvidence) {
            // Retain the complete package record, including explicit actor and subject kinds.
            $payload = $evidence->toArray();
            $actorId = $payload['actor']['id'];
            $action = $payload['action'];
            $subjectType = $payload['subject']['kind'];
            $subjectId = $payload['subject']['id'];
            $context = $payload;
        } else {
            $actorId = (string) $evidence->actorId;
            $action = match (true) {
                $evidence instanceof BootstrapEvidence      => 'user.bootstrap_completed',
                $evidence instanceof ConsoleRoleEvidence    => 'user.console_role_assigned',
                $evidence instanceof InvitationRoleEvidence => 'user.invitation_role_confirmed'
            };
            $subjectType = 'user';
            $subjectId = $evidence->userId->toString();
            // Preserve the existing owned facts and historical row shape.
            $context = $evidence instanceof BootstrapEvidence ? (object) [] : [
                'role_id' => $evidence->roleId->toString(),
                'result'  => 'membership_confirmed'
            ];
        }

        $json = json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 4096) {
            throw new InvalidArgumentException('Audit evidence exceeds its storage bound.');
        }

        $this->connection->insert('audit_evidence', [
            'actor_id'     => $actorId,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'context'      => $json
        ]);
    }
}
