<?php

declare(strict_types=1);

namespace App\Application\Workspace\Project\QueryHandler;

use App\Application\Workspace\Project\View\ProjectConflicts;
use App\Domain\Workspace\Project\ProjectRepository;
use App\Domain\Workspace\Project\Query\PreviewProjectConflicts;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use InvalidArgumentException;

/**
 * Class PreviewProjectConflictsHandler
 */
final readonly class PreviewProjectConflictsHandler implements QueryHandler
{
    /**
     * Constructs PreviewProjectConflictsHandler
     */
    public function __construct(private ProjectRepository $projectRepository)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return PreviewProjectConflicts::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ProjectConflicts
    {
        $query = $queryMessage->payload();
        if (!$query instanceof PreviewProjectConflicts) {
            throw new InvalidArgumentException('Unexpected Project query.');
        }
        $sourceClaimed = false;
        foreach ($query->sources->all() as $source) {
            if ($this->projectRepository->hasActiveSource($query->workspaceId, $source)) {
                $sourceClaimed = true;
                break;
            }
        }

        return new ProjectConflicts(
            $this->projectRepository->hasActiveSlug($query->workspaceId, $query->metadata->slug),
            $sourceClaimed
        );
    }
}
