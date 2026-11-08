<?php

declare(strict_types=1);

namespace App\Application\Workspace\Project\QueryHandler;

use App\Application\Workspace\Project\View\ProjectView;
use App\Domain\Workspace\Project\ProjectRepository;
use App\Domain\Workspace\Project\Query\GetProjectById;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use InvalidArgumentException;

/**
 * Class GetProjectByIdHandler
 */
final readonly class GetProjectByIdHandler implements QueryHandler
{
    /**
     * Constructs GetProjectByIdHandler
     */
    public function __construct(private ProjectRepository $projectRepository)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return GetProjectById::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ProjectView
    {
        $query = $queryMessage->payload();
        if (!$query instanceof GetProjectById) {
            throw new InvalidArgumentException('Unexpected Project query.');
        }
        $project = $this->projectRepository->getById($query->workspaceId, $query->projectId);
        if ($project === null) {
            throw new ResourceNotFound();
        }

        return ProjectView::fromProject($project);
    }
}
