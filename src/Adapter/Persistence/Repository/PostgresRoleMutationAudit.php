<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use App\Domain\Authorization\RoleMutationAudit;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\Role\Event\CustomRoleCreated;
use Fight\AccessControl\Domain\AccessControl\Role\Event\CustomRoleRemoved;
use Fight\AccessControl\Domain\AccessControl\Role\Event\CustomRoleRenamed;
use LogicException;

/**
 * Class PostgresRoleMutationAudit
 */
final readonly class PostgresRoleMutationAudit implements RoleMutationAudit
{
    /**
     * Constructs PostgresRoleMutationAudit
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function record(CustomRoleCreated|CustomRoleRenamed|CustomRoleRemoved $event): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Role audit requires the mutation transaction.');
        }
        $action = match (true) {
            $event instanceof CustomRoleCreated => 'role.custom_created',
            $event instanceof CustomRoleRenamed => 'role.custom_renamed',
            $event instanceof CustomRoleRemoved => 'role.custom_removed'
        };
        $this->connection->insert('audit_evidence', [
            'actor_id'     => $event->getActorId()->toString(),
            'action'       => $action,
            'subject_type' => 'role',
            'subject_id'   => $event->getRoleId()->toString(),
            'context'      => '{}'
        ]);
    }
}
