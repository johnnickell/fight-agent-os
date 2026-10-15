<?php

declare(strict_types=1);

namespace App\Domain\Bootstrap;

/**
 * Class BootstrapState
 *
 * Reports inspected step facts without credentials or an implication of cross-step rollback
 */
final readonly class BootstrapState
{
    /**
     * Constructs BootstrapState
     */
    public function __construct(
        public string $admission,
        public string $invitation = 'unknown',
        public string $assignment = 'unknown',
        public string $audit = 'unconfirmed'
    ) {
    }

    /**
     * Determines whether all required final evidence was independently established
     */
    public function isComplete(): bool
    {
        return $this->admission === 'admitted' && $this->invitation === 'confirmed'
            && $this->assignment === 'confirmed' && $this->audit === 'confirmed';
    }
}
