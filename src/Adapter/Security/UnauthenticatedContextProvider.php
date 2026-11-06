<?php

declare(strict_types=1);

namespace App\Adapter\Security;

use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContextProvider;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;

/**
 * Class UnauthenticatedContextProvider
 *
 * Denies shared command authentication until an invocation explicitly binds verified context
 */
final readonly class UnauthenticatedContextProvider implements AuthenticationContextProvider
{
    /**
     * @inheritDoc
     */
    public function getAuthenticationContext(): AuthenticationContext
    {
        throw new AuthenticationRequired();
    }
}
