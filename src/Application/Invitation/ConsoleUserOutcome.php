<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class ConsoleUserOutcome
 *
 * Reports inspected safe state independently from execution and audit certainty
 */
final readonly class ConsoleUserOutcome
{
    /**
     * Constructs ConsoleUserOutcome
     */
    public function __construct(
        public bool $success,
        public ?UserId $knownUserId,
        public string $assignment,
        public string $audit
    ) {
    }
}
