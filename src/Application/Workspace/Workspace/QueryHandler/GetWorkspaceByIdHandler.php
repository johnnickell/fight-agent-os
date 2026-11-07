<?php

declare(strict_types=1);

namespace App\Application\Workspace\Workspace\QueryHandler;

use App\Application\Workspace\Workspace\View\WorkspaceView;
use App\Domain\Workspace\Workspace\Query\GetWorkspaceById;
use App\Domain\Workspace\Workspace\WorkspaceRepository;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use InvalidArgumentException;

/**
 * Class GetWorkspaceByIdHandler
 *
 * Projects required current Workspace facts and distinguishes absence from lookup failure
 */
final readonly class GetWorkspaceByIdHandler implements QueryHandler
{
    /**
     * Constructs GetWorkspaceByIdHandler
     */
    public function __construct(private WorkspaceRepository $workspaceRepository)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return GetWorkspaceById::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): WorkspaceView
    {
        $query = $queryMessage->payload();
        if (!$query instanceof GetWorkspaceById) {
            throw new InvalidArgumentException('Unexpected Workspace query.');
        }
        $workspace = $this->workspaceRepository->getById($query->workspaceId);
        if ($workspace === null) {
            throw new ResourceNotFound();
        }

        return WorkspaceView::fromWorkspace($workspace);
    }
}
