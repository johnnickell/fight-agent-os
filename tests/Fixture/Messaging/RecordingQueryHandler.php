<?php

declare(strict_types=1);

namespace Tests\Fixture\Messaging;

use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

final class RecordingQueryHandler implements QueryHandler
{
    public static function queryRegistration(): string
    {
        return ReadJourneyQuery::class;
    }

    public function handle(QueryMessage $queryMessage): mixed
    {
        /** @var ReadJourneyQuery $query */
        $query = $queryMessage->payload();

        return sprintf('answer:%s', $query->value);
    }
}
