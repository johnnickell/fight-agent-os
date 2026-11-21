<?php

declare(strict_types=1);

namespace App\Application\Security\Session;

use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\Common\Domain\Repository\ResultSet;

/**
 * Class SessionPage
 *
 * Carries authoritative session views and optional device hints for presentation
 */
final readonly class SessionPage
{
    /**
     * Constructs SessionPage
     *
     * @param ResultSet $sessions
     * @param array $devices
     *
     * @phpstan-param ResultSet<SessionView> $sessions
     * @phpstan-param array<string, DeviceDescriptor> $devices
     */
    public function __construct(public ResultSet $sessions, private array $devices = [])
    {
    }

    /**
     * Returns the stored hint or an unknown device without implying session authority
     */
    public function deviceFor(RefreshSessionId $id): DeviceDescriptor
    {
        return $this->devices[$id->toString()] ?? new DeviceDescriptor();
    }
}
