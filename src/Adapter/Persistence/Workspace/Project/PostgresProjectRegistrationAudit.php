<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Workspace\Project;

use App\Application\Workspace\Project\ProjectRegistrationAudit;
use App\Domain\Workspace\Project\Project;
use Doctrine\DBAL\Connection;
use Fight\Common\Domain\Messaging\MessageId;
use LogicException;

/**
 * Class PostgresProjectRegistrationAudit
 */
final readonly class PostgresProjectRegistrationAudit implements ProjectRegistrationAudit
{
    /**
     * Constructs PostgresProjectRegistrationAudit
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function record(Project $project): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Project audit requires an enclosing transaction.');
        }
        $this->connection->insert('workspace_audit', [
            'id'                   => MessageId::generate()->toString(),
            'workspace_id'         => $project->workspaceId->toString(),
            'project_id'           => $project->id->toString(),
            'actor_type'           => 'user',
            'actor_id'             => $project->createdBy->toString(),
            'action'               => 'project_registered',
            'predecessor_revision' => 0,
            'result_revision'      => 1,
            'result_code'          => 'created',
            'created_at'           => $project->createdAt->format('Y-m-d H:i:s.uP')
        ]);
    }
}
