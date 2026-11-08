<?php

declare(strict_types=1);

namespace App\Application\Workspace\Project\View;

/**
 * Class ProjectPage
 */
final readonly class ProjectPage
{
    /**
     * Constructs ProjectPage
     *
     * @phpstan-param list<ProjectView> $items
     */
    public function __construct(public array $items, public bool $hasMore, public int $offset, public int $limit)
    {
    }
}
