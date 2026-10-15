<?php

declare(strict_types=1);

namespace App\Application\Bootstrap;

use App\Application\Invitation\ConsoleCommandIntent;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Bootstrap\BootstrapClaims;
use App\Domain\Bootstrap\BootstrapState;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use LogicException;
use Throwable;

/**
 * Class BootstrapOperation
 *
 * Coordinates one acknowledged claim with independent package steps and safe inspected outcomes
 */
final class BootstrapOperation
{
    private bool $active = false;
    private bool $won = false;

    /**
     * Constructs BootstrapOperation
     */
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly BootstrapClaims $claims,
        private readonly ConsoleCommandIntent $intent,
        private readonly BootstrapClaimScope $scope
    ) {
    }

    /**
     * Runs once without retrying any uncertain mutation or resuming an existing claim
     */
    public function run(BootstrapSuperAdmin $command): BootstrapState
    {
        if ($this->active) {
            throw new LogicException('Bootstrap invocations cannot overlap.');
        }
        $this->scope->open($command);
        $this->active = true;
        $this->won = false;
        try {
            try {
                $this->intent->dispatch($this->commands, $command);
            } catch (Throwable) {
                // An exception never proves rollback across independent commits; inspect actual state.
            }

            return $this->inspect($command);
        } finally {
            $this->scope->close();
            $this->active = false;
            $this->won = false;
        }
    }

    /**
     * Performs only this invocation's terminally validated request through configured package buses
     */
    public function perform(BootstrapSuperAdmin $command): void
    {
        if (!$this->active || $this->won) {
            throw new LogicException('A single guarded bootstrap invocation is required.');
        }
        $this->intent->dispatch($this->commands, new ReconcileManagedPolicy(InstallationManagedPolicy::create()));
        $this->requireInstallation();
        $this->claims->claim($command);
        $this->won = true;
        $this->scope->acknowledgeClaim();
        $this->intent->dispatch($this->commands, new InvitePendingUser(
            $command->input->actorId(),
            $command->userId,
            $command->input->email
        ));
        $this->intent->dispatch($this->commands, new AssignRoleToUser(
            $command->input->operatorId,
            $command->userId,
            RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID)
        ));
        $state = $this->claims->inspect($command);
        if ($state->claim !== 'claimed' || $state->invitation !== 'confirmed' || $state->assignment !== 'confirmed') {
            throw new LogicException('Bootstrap membership was not established.');
        }
        $this->claims->complete($command);
    }

    /**
     * Requires current fixed managed authority and its exact stable elevated role reference
     */
    public function requireInstallation(): void
    {
        InstallationManagedPolicy::requireReconciled($this->queries->fetch(
            new PreviewManagedPolicy(InstallationManagedPolicy::create())
        ));
        $roleId = RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID);
        $role = $this->queries->fetch(new GetRoleById($roleId));
        if (!$role instanceof RoleView || !$role->getRoleId()->equals($roleId) || !$role->isManaged()) {
            throw new LogicException('Managed bootstrap authority is unavailable.');
        }
    }

    /**
     * Returns fresh safe state without treating a previous invocation as this invocation's success
     */
    private function inspect(BootstrapSuperAdmin $command): BootstrapState
    {
        try {
            $state = $this->claims->inspect($command);
            if (!$this->won && $state->isComplete()) {
                return new BootstrapState('unavailable');
            }

            return $state;
        } catch (Throwable) {
            return new BootstrapState('unknown');
        }
    }
}
