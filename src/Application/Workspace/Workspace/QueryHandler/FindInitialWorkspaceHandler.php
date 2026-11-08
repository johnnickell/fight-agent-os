<?php

declare(strict_types=1);

namespace App\Application\Workspace\Workspace\QueryHandler;

use App\Application\Workspace\Workspace\View\WorkspaceView;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use App\Domain\Workspace\Workspace\WorkspaceRepository;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use InvalidArgumentException;

/**
 * Class FindInitialWorkspaceHandler
 *
 * Projects current optional initial Workspace facts without creating state
 */
final readonly class FindInitialWorkspaceHandler implements QueryHandler
{
    /**
     * Constructs FindInitialWorkspaceHandler
     */
    public function __construct(private WorkspaceRepository $workspaceRepository)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return FindInitialWorkspace::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ?WorkspaceView
    {
        if (!$queryMessage->payload() instanceof FindInitialWorkspace) {
            throw new InvalidArgumentException('Unexpected Workspace query.');
        }
        $workspace = $this->workspaceRepository->findInitial();

        return $workspace === null ? null : WorkspaceView::fromWorkspace($workspace);
    }
}
