<?php

declare(strict_types=1);

namespace App\Domain\Invitation;

use InvalidArgumentException;

/**
 * Class InvitationRatePolicy
 *
 * Bounds fixed-window invitation budgets and retained actor/action keys
 */
final readonly class InvitationRatePolicy
{
    /**
     * Constructs InvitationRatePolicy
     */
    public function __construct(
        public int $windowSeconds = 60,
        public int $issueLimit = 10,
        public int $statusLimit = 60,
        public int $capacity = 10000,
        public int $retryLimit = 5,
        public int $resendLimit = 3,
        public int $correctLimit = 3
    ) {
        if (
            $windowSeconds < 1 || $windowSeconds > 86400 || $issueLimit < 1 || $issueLimit > 1000
            || $statusLimit < 1 || $statusLimit > 10000 || $capacity < 1 || $capacity > 100000
            || $retryLimit < 1 || $retryLimit > 1000 || $resendLimit < 1 || $resendLimit > 1000
            || $correctLimit < 1 || $correctLimit > 1000
        ) {
            throw new InvalidArgumentException('Invalid invitation rate policy.');
        }
    }

    /**
     * Returns the configured operation budget
     */
    public function limit(InvitationOperation $operation): int
    {
        return match ($operation) {
            InvitationOperation::ISSUE => $this->issueLimit,
            InvitationOperation::STATUS => $this->statusLimit,
            InvitationOperation::RETRY => $this->retryLimit,
            InvitationOperation::RESEND => $this->resendLimit,
            InvitationOperation::CORRECT => $this->correctLimit
        };
    }
}
