<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Workspace\Workspace;

use App\Application\Workspace\Workspace\WorkspaceBootstrapAudit;
use App\Domain\Workspace\Workspace\Workspace;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Messaging\MessageId;
use LogicException;

/**
 * Class PostgresWorkspaceBootstrapAudit
 *
 * Appends required bootstrap audit without a response snapshot or replay record
 */
final readonly class PostgresWorkspaceBootstrapAudit implements WorkspaceBootstrapAudit
{
    /**
     * Constructs PostgresWorkspaceBootstrapAudit
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function record(Workspace $workspace, UserId $operatorId): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Workspace audit requires an enclosing transaction.');
        }
        $this->connection->insert('workspace_audit', [
            'id'                   => MessageId::generate()->toString(),
            'workspace_id'         => $workspace->id->toString(),
            'repository_id'        => null,
            'actor_type'           => 'installation_operator',
            'actor_id'             => $operatorId->toString(),
            'action'               => 'workspace_bootstrapped',
            'predecessor_revision' => 0,
            'result_revision'      => 1,
            'result_code'          => 'created',
            'created_at'           => $workspace->createdAt->format('Y-m-d H:i:s.uP')
        ]);
    }
}
