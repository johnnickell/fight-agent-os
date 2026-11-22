<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Sessions;

use App\Application\Security\Session\SessionPage;
use DateTimeZone;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class OwnedSessionsData
 *
 * Maps explicit safe fields without redundant owner or credential state
 */
final readonly class OwnedSessionsData implements Arrayable
{
    /**
     * Constructs OwnedSessionsData
     */
    public function __construct(private SessionPage $page)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $records = [];
        $utc = new DateTimeZone('UTC');
        $sessions = $this->page->sessions;
        foreach ($sessions->records() as $session) {
            $device = $this->page->deviceFor($session->getSessionId());
            $records[] = [
                'session_id'          => $session->getSessionId()->toString(),
                'browser_family'      => $device->browserFamily->value,
                'device_class'        => $device->deviceClass->value,
                'created_at'          => $session->getCreatedAt()->setTimezone($utc)->format(DATE_ATOM),
                'last_activity_at'    => $session->getLastActivityAt()->setTimezone($utc)->format(DATE_ATOM),
                'idle_expires_at'     => $session->getIdleExpiresAt()->setTimezone($utc)->format(DATE_ATOM),
                'absolute_expires_at' => $session->getAbsoluteExpiresAt()->setTimezone($utc)->format(DATE_ATOM),
                'remembered'          => $session->isRemembered(),
                'current'             => $session->isCurrent()
            ];
        }

        return [
            'page'          => $sessions->page(),
            'per_page'      => $sessions->perPage(),
            'total_pages'   => $sessions->totalPages(),
            'total_records' => $sessions->totalRecords(),
            'records'       => $records
        ];
    }
}
