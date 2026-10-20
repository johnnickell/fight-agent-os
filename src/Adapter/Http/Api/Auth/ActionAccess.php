<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Auth;

use App\Adapter\Http\Action\Api\V1\Auth\ActivateInvitationAction;
use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Action\Api\V1\Auth\LoginAction;
use App\Adapter\Http\Action\Api\V1\Validations\ReadValidationAction;
use App\Adapter\Http\Attribute\Authenticated;
use App\Adapter\Http\Attribute\PublicAccess;
use App\Adapter\Http\Attribute\RequiresPermission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use LogicException;
use ReflectionClass;
use Throwable;

/**
 * Class ActionAccess
 *
 * Inspects only the matched final Action without resolving its constructor
 */
final readonly class ActionAccess
{
    /**
     * Constructs ActionAccess
     *
     * @param boolean                 $public
     * @param PermissionName[] $permissions
     *
     * @phpstan-param list<PermissionName> $permissions
     */
    private function __construct(public bool $public, public array $permissions)
    {
    }

    /**
     * Requires one valid class-level security declaration on the executed Action
     */
    public static function inspect(mixed $callable): self
    {
        try {
            if (!is_string($callable) || !str_ends_with($callable, ':handle')) {
                throw new LogicException();
            }
            $name = substr($callable, 0, -strlen(':handle'));
            if (!class_exists($name)) {
                throw new LogicException();
            }
            $class = new ReflectionClass($name);
            $method = $class->getMethod('handle');
            if (!$class->isFinal() || $class->isAbstract() || !$method->isPublic() || $method->isStatic()) {
                throw new LogicException();
            }
            $public = $class->getAttributes(PublicAccess::class);
            $authenticated = $class->getAttributes(Authenticated::class);
            $required = $class->getAttributes(RequiresPermission::class);
            foreach ([PublicAccess::class, Authenticated::class, RequiresPermission::class] as $attribute) {
                if ($method->getAttributes($attribute) !== []) {
                    throw new LogicException();
                }
            }
            if (
                count($public) > 1 || count($authenticated) > 1
                || (int) ($public !== []) + (int) ($authenticated !== []) + (int) ($required !== []) !== 1
            ) {
                throw new LogicException();
            }
            foreach ([...$public, ...$authenticated] as $marker) {
                if ($marker->getArguments() !== []) {
                    throw new LogicException();
                }
                $marker->newInstance();
            }
            if (
                $public !== [] && !in_array($class->getName(), [
                CsrfBootstrapAction::class, ReadValidationAction::class, ActivateInvitationAction::class,
                LoginAction::class
                ], true)
            ) {
                throw new LogicException();
            }
            $permissions = [];
            foreach ($required as $marker) {
                $permission = $marker->newInstance();
                $permissions[] = PermissionName::fromString($permission->name);
            }

            return new self($public !== [], $permissions);
        } catch (Throwable) {
            throw new LogicException('Invalid API Action access declaration.');
        }
    }
}
