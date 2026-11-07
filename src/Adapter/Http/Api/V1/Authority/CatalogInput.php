<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Authority;

use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class CatalogInput
 */
final class CatalogInput
{
    /**
     * Requires verified invocation-bound authentication for Application revalidation
     */
    public static function authentication(ServerRequestInterface $request): AuthenticationContext
    {
        $authentication = $request->getAttribute(AuthenticationContext::class);
        if (!$authentication instanceof AuthenticationContext) {
            throw new AuthenticationRequired();
        }

        return $authentication;
    }
}
