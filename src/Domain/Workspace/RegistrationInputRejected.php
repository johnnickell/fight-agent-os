<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use DomainException;

/**
 * Class RegistrationInputRejected
 *
 * Carries no submitted locator or private diagnostic
 */
final class RegistrationInputRejected extends DomainException
{
    /**
     * Constructs RegistrationInputRejected
     */
    public function __construct()
    {
        parent::__construct('Invalid repository registration input.');
    }
}
