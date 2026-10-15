<?php

declare(strict_types=1);

namespace App\Application\Bootstrap;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Bootstrap\BootstrapProgress;
use App\Domain\Bootstrap\BootstrapState;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use LogicException;
use Throwable;

/**
 * Class BootstrapOperation
 *
 * Coordinates a trusted manual bootstrap with independent package commits and inspected outcomes
 */
final readonly class BootstrapOperation
{
    /**
     * Constructs BootstrapOperation
     */
    public function __construct(
        private CommandBus $commands,
        private QueryBus $queries,
        private UserRepository $users,
        private BootstrapProgress $progress
    ) {
    }

    /**
     * Runs bootstrap only after an authoritative absence check permits the manual attempt
     */
    public function run(BootstrapSuperAdmin $command): BootstrapState
    {
        $roleId = RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID);
        try {
            if ($this->users->hasRoleAssignment($roleId)) {
                return new BootstrapState('refused');
            }
        } catch (Throwable) {
            return new BootstrapState('unknown');
        }
        try {
            $this->commands->execute(new ReconcileManagedPolicy(InstallationManagedPolicy::create()));
            InstallationManagedPolicy::requireReconciled($this->queries->fetch(
                new PreviewManagedPolicy(InstallationManagedPolicy::create())
            ));
            $role = $this->queries->fetch(new GetRoleById($roleId));
            if (!$role instanceof RoleView || !$role->getRoleId()->equals($roleId) || !$role->isManaged()) {
                throw new LogicException('Managed bootstrap authority is unavailable.');
            }
            $this->commands->execute(new InvitePendingUser(
                $command->input->actorId(),
                $command->userId,
                $command->input->email
            ));
            $this->commands->execute(new AssignRoleToUser($command->input->operatorId, $command->userId, $roleId));
            $this->progress->complete($command);
        } catch (Throwable) {
            // An exception never proves rollback across independently committed package steps.
        }
        try {
            return $this->progress->inspect($command);
        } catch (Throwable) {
            return new BootstrapState('admitted');
        }
    }
}
