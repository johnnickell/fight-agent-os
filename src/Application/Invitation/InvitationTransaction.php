<?php

declare(strict_types=1);

namespace App\Application\Invitation;

/**
 * Interface InvitationTransaction
 *
 * Commits invitation work under stable authority before publishing package events
 */
interface InvitationTransaction
{
    /**
     * Executes all originating work atomically and discards events on rollback
     *
     * Post-commit event failure propagates without undoing the committed invitation
     *
     * @phpstan-param callable(): void $operation
     */
    public function commit(callable $operation): void;
}
