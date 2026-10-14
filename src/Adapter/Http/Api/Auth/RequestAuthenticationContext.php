<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Auth;

use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContextProvider;

/**
 * Class RequestAuthenticationContext
 *
 * Supplies immutable verified authentication to one package current-principal provider
 */
final readonly class RequestAuthenticationContext implements AuthenticationContextProvider
{
    /**
     * Constructs RequestAuthenticationContext
     */
    public function __construct(private AuthenticationContext $context)
    {
    }

    /**
     * @inheritDoc
     */
    public function getAuthenticationContext(): AuthenticationContext
    {
        return $this->context;
    }
}
