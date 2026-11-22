<?php

declare(strict_types=1);

namespace App\Application\Security\Session\QueryHandler;

use App\Application\Security\Session\SessionPage;
use App\Application\Security\Session\SessionPresentation;
use App\Domain\Security\Session\Query\ListOwnedSessions;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\ListActiveSessions;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Repository\ResultSet;
use Throwable;

/**
 * Class ListOwnedSessionsHandler
 *
 * Adds optional presentation to the authoritative package page
 */
final readonly class ListOwnedSessionsHandler implements QueryHandler
{
    /**
     * Constructs ListOwnedSessionsHandler
     */
    public function __construct(private QueryBus $queryBus, private SessionPresentation $presentation)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return ListOwnedSessions::class;
    }

    /**
     * Reads only device descriptors for sessions returned by the package
     */
    public function handle(QueryMessage $queryMessage): SessionPage
    {
        /** @var ListOwnedSessions $query */
        $query = $queryMessage->payload();
        /** @var ResultSet<SessionView> $sessions */
        $sessions = $this->queryBus->fetch(new ListActiveSessions(
            $query->userId,
            $query->userId,
            $query->currentSessionId,
            $query->pagination()
        ));
        $ids = [];
        foreach ($sessions->records() as $session) {
            $ids[] = $session->getSessionId()->toString();
        }
        try {
            $devices = $this->presentation->descriptors($ids);
        } catch (Throwable) {
            $devices = [];
        }

        return new SessionPage($sessions, $devices);
    }
}
