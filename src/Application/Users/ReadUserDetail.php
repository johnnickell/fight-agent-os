<?php

declare(strict_types=1);

namespace App\Application\Users;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Failure\PermissionDenied;
use App\Application\Failure\ResourceNotFound;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Query\QueryBus;
use LogicException;
use Throwable;

/**
 * Class ReadUserDetail
 *
 * Authorizes one installation user read before any package target lookup
 */
final readonly class ReadUserDetail
{
    /**
     * Constructs ReadUserDetail
     */
    public function __construct(
        private AuthenticatedPermissions $authentication,
        private InstallationAuthority $installation,
        private QueryBus $queries,
        private UserRolePresentation $roles
    ) {
    }

    /**
     * Reads safe package state without ownership fallback or caller-supplied authority
     *
     * @return array{user: UserView, roles: list<array{name: string, managed: bool}>}
     */
    public function read(AuthenticationContext $authentication, UserId $id): array
    {
        $permission = PermissionName::fromString('READ_USERS');
        $actor = $this->authentication->require($authentication, [$permission]);
        try {
            $this->installation->assertInstallationPermission($actor, $permission);
        } catch (Throwable) {
            throw new PermissionDenied();
        }
        $user = $this->queries->fetch(new GetUserById($id));
        if ($user === null) {
            throw new ResourceNotFound();
        }
        if (!$user instanceof UserView || !$user->getUserId()->equals($id)) {
            throw new LogicException('User detail is unavailable.');
        }

        return ['user' => $user, 'roles' => $this->roles->resolve([$user])[$id->toString()]];
    }
}
