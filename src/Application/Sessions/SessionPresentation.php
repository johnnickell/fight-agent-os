<?php

declare(strict_types=1);

namespace App\Application\Sessions;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;

/**
 * Interface SessionPresentation
 *
 * Provides non-authoritative coarse session presentation persistence
 */
interface SessionPresentation
{
    /**
     * Stores the original descriptor once without extending retention on refresh
     */
    public function capture(RefreshSessionId $id, DeviceDescriptor $device, DateTimeImmutable $expiresAt): void;

    /**
     * Reads only descriptors for the requested authoritative session page
     *
     * @param array $ids
     *
     * @phpstan-param list<string> $ids
     *
     * @return array<string, DeviceDescriptor>
     */
    public function descriptors(array $ids): array;

    /**
     * Deletes a bounded batch whose authoritative sessions are absent or no longer usable
     */
    public function prune(int $limit): int;
}
