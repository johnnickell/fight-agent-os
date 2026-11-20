<?php

declare(strict_types=1);

namespace App\Application\AccessControl\User\View;

use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;

/**
 * Class DirectoryUser
 *
 * Pairs a safe User View with its resolved Roles
 */
final readonly class DirectoryUser
{
    /**
     * Constructs DirectoryUser
     *
     * @phpstan-param list<RoleView> $roles
     */
    public function __construct(public UserView $user, public array $roles)
    {
    }
}
