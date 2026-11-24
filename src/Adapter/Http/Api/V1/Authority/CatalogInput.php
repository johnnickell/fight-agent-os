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
     * Maps validated public sort names to package ordering keys
     *
     * @return array<string, string>
     */
    public static function orderings(?string $sort, ?string $order): array
    {
        if ($sort === null && $order === null) {
            return [];
        }

        return [($sort === 'ID' ? 'id' : 'created_at') => $order ?? 'asc'];
    }

    /**
     * Reads the verified request authentication established by the HTTP gate
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
