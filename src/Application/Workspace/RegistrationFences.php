<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;

/**
 * Interface RegistrationFences
 */
interface RegistrationFences
{
    /**
     * Enforces current permission, assignment, user and session fences until the owning transaction ends
     */
    public function holdAuthority(AuthenticationContext $authentication): void;
}
