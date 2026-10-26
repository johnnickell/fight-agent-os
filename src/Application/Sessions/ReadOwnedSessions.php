<?php

declare(strict_types=1);

namespace App\Application\Sessions;

use App\Application\Authorization\PermissionGuard;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\ListActiveSessions;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Class ReadOwnedSessions
 *
 * Enforces principal-only ownership and exact permission before the package-owned session query
 */
final readonly class ReadOwnedSessions
{
    /**
     * Constructs ReadOwnedSessions
     */
    public function __construct(
        private PermissionGuard $permissions,
        private QueryBus $queries,
        private SessionPresentation $presentation
    ) {
    }

    /**
     * Reads one authoritative page and optional presentation without accepting identity or ordering input
     *
     * @return array{sessions: ResultSet<SessionView>, devices: array<string, DeviceDescriptor>}
     */
    public function read(CurrentPrincipalProvider $provider, int $page = 1, int $perPage = 25): array
    {
        $principal = $provider->getCurrentPrincipal();
        $this->permissions->assertAllowed(
            new SecurityContext($principal),
            [PermissionName::fromString('READ_OWN_SESSIONS')]
        );
        if ($page < 1 || $page > 1000 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid session pagination.');
        }
        $result = $this->queries->fetch(new ListActiveSessions(
            $principal->getUserId(),
            $principal->getUserId(),
            $principal->getRefreshSessionId(),
            new Pagination($page, $perPage)
        ));
        if (!$result instanceof ResultSet || $result->itemType() !== SessionView::class) {
            throw new LogicException('Session listing is unavailable.');
        }
        $ids = [];
        foreach ($result->records() as $session) {
            if (!$session->getUserId()->equals($principal->getUserId())) {
                throw new LogicException('Session listing is unavailable.');
            }
            $ids[] = $session->getSessionId()->toString();
        }
        try {
            $devices = $this->presentation->descriptors($ids);
        } catch (Throwable) {
            $devices = [];
        }

        return ['sessions' => $result, 'devices' => $devices];
    }
}
