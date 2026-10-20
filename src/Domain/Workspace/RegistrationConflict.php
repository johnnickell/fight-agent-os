<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use DomainException;

/**
 * Class RegistrationConflict
 *
 * Reports only a bounded claim or original-intent conflict without identifying another registration
 */
final class RegistrationConflict extends DomainException
{
    /**
     * Constructs RegistrationConflict
     */
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Repository registration conflicts.');
    }
}
