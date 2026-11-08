<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Workspace\Query;

use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class FindInitialWorkspace
 *
 * Requests current optional initial Workspace state without bootstrap or replay
 */
final readonly class FindInitialWorkspace implements Query
{
    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self();
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [];
    }
}
