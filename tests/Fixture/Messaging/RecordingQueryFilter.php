<?php

declare(strict_types=1);

namespace Tests\Fixture\Messaging;

use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

final class RecordingQueryFilter implements QueryFilter
{
    /** @var list<string> */
    public array $processed = [];

    public function process(QueryMessage $queryMessage, callable $next): void
    {
        $this->processed[] = $queryMessage->payload()::class;
        $next($queryMessage);
    }
}
