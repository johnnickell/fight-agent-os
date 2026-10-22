<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Authority;

use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRevision;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class RoleMutationInput
 */
final class RoleMutationInput
{
    /**
     * Parses a canonical package identifier without disclosing submitted values
     */
    public static function id(ServerRequestInterface $request): RoleId
    {
        try {
            return RoleId::fromString((string) RouteContext::fromRequest($request)->getRoute()?->getArgument('roleId'));
        } catch (Throwable) {
            throw new ValidationException(['role_id' => ['Invalid value.']]);
        }
    }

    /**
     * Parses an exact caller revision without any fallback read
     */
    public static function revision(string $value): RoleRevision
    {
        try {
            return RoleRevision::fromString($value);
        } catch (Throwable) {
            throw new ValidationException(['expected_revision' => ['Invalid value.']]);
        }
    }

    /**
     * Parses a package-owned canonical Role name
     */
    public static function name(string $value): RoleName
    {
        try {
            return RoleName::fromString($value);
        } catch (Throwable) {
            throw new ValidationException(['name' => ['Invalid value.']]);
        }
    }
}
