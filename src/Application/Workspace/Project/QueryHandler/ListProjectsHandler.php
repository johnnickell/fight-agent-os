<?php

declare(strict_types=1);

namespace App\Application\Workspace\Project\QueryHandler;

use App\Application\Workspace\Project\View\ProjectPage;
use App\Application\Workspace\Project\View\ProjectView;
use App\Domain\Workspace\Project\ProjectRepository;
use App\Domain\Workspace\Project\Query\ListProjects;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use InvalidArgumentException;

/**
 * Class ListProjectsHandler
 */
final readonly class ListProjectsHandler implements QueryHandler
{
    /**
     * Constructs ListProjectsHandler
     */
    public function __construct(private ProjectRepository $projectRepository)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return ListProjects::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ProjectPage
    {
        $query = $queryMessage->payload();
        if (!$query instanceof ListProjects) {
            throw new InvalidArgumentException('Unexpected Project query.');
        }
        $projects = $this->projectRepository->slice($query->workspaceId, $query->offset, $query->limit + 1);

        return new ProjectPage(
            array_map(ProjectView::fromProject(...), array_slice($projects, 0, $query->limit)),
            count($projects) > $query->limit,
            $query->offset,
            $query->limit
        );
    }
}
