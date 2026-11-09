<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Users;

use App\Adapter\Http\Attribute\JsonBody;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class LifecycleInput
 *
 * Maps principal-only actor context and confirmed route-only lifecycle targets
 */
final class LifecycleInput
{
    /**
     * Requires the centrally authenticated caller without accepting claimed actor fields
     */
    public static function authentication(ServerRequestInterface $request): AuthenticationContext
    {
        $authentication = $request->getAttribute(AuthenticationContext::class);
        if (!$authentication instanceof AuthenticationContext) {
            throw new AuthenticationRequired();
        }

        return $authentication;
    }

    /**
     * Validates explicit intent and the bounded target ID after normal input admission
     */
    public static function target(ServerRequestInterface $request): UserId
    {
        /** @var array{confirm: bool} $input */
        $input = $request->getAttribute(JsonBody::class);
        if ($input['confirm'] !== true) {
            throw new ValidationException(['confirm' => ['Explicit confirmation is required.']]);
        }
        $value = (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('userId');
        try {
            if (strlen($value) !== 36) {
                throw new \InvalidArgumentException();
            }

            return UserId::fromString($value);
        } catch (Throwable) {
            throw new ValidationException(['user_id' => ['Invalid value.']]);
        }
    }
}
