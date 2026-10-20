<?php

declare(strict_types=1);

namespace Tests\Support;

use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class CatalogQueryProbe
 *
 * Observes real configured query dispatch without replacing the bus or its handlers
 */
final class CatalogQueryProbe implements QueryFilter
{
    private int $calls = 0;

    /**
     * @inheritDoc
     */
    public function process(QueryMessage $queryMessage, callable $next): void
    {
        ++$this->calls;
        $next($queryMessage);
    }

    /**
     * Returns the observed query count
     */
    public function count(): int
    {
        return $this->calls;
    }
}
