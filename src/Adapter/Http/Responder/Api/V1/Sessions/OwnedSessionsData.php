<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Sessions;

use App\Application\Sessions\DeviceDescriptor;
use DateTimeZone;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\Common\Domain\Repository\ResultSet;
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
     *
     * @param ResultSet $sessions
     * @param array $devices
     *
     * @phpstan-param ResultSet<SessionView> $sessions
     * @phpstan-param array<string, DeviceDescriptor> $devices
     */
    public function __construct(private ResultSet $sessions, private array $devices)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $records = [];
        $utc = new DateTimeZone('UTC');
        foreach ($this->sessions->records() as $session) {
            $device = $this->devices[$session->getSessionId()->toString()] ?? new DeviceDescriptor();
            $records[] = [
                'session_id'          => $session->getSessionId()->toString(),
                'browser_family'      => $device->browser->value,
                'device_class'        => $device->device->value,
                'created_at'          => $session->getCreatedAt()->setTimezone($utc)->format(DATE_ATOM),
                'last_activity_at'    => $session->getLastActivityAt()->setTimezone($utc)->format(DATE_ATOM),
                'idle_expires_at'     => $session->getIdleExpiresAt()->setTimezone($utc)->format(DATE_ATOM),
                'absolute_expires_at' => $session->getAbsoluteExpiresAt()->setTimezone($utc)->format(DATE_ATOM),
                'remembered'          => $session->isRemembered(),
                'current'             => $session->isCurrent()
            ];
        }

        return [
            'page'          => $this->sessions->page(),
            'per_page'      => $this->sessions->perPage(),
            'total_pages'   => $this->sessions->totalPages(),
            'total_records' => $this->sessions->totalRecords(),
            'records'       => $records
        ];
    }
}
