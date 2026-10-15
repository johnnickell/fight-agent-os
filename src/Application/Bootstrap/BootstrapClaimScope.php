<?php

declare(strict_types=1);

namespace App\Application\Bootstrap;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use LogicException;

/**
 * Class BootstrapClaimScope
 *
 * Limits one invitation and elevation to the exact invocation whose permanent claim was acknowledged
 */
final class BootstrapClaimScope
{
    private ?BootstrapSuperAdmin $command = null;
    private bool $claimed = false;
    private bool $invited = false;
    private bool $assigned = false;

    /**
     * Opens a new non-overlapping invocation bound to its immutable operator and target
     */
    public function open(BootstrapSuperAdmin $command): void
    {
        if ($this->command !== null) {
            throw new LogicException('Bootstrap scope is already active.');
        }
        $this->command = $command;
        $this->claimed = false;
        $this->invited = false;
        $this->assigned = false;
    }

    /**
     * Records acknowledged claim commit for this invocation only
     */
    public function acknowledgeClaim(): void
    {
        if ($this->command === null || $this->claimed) {
            throw new LogicException('Bootstrap claim scope is unavailable.');
        }
        $this->claimed = true;
    }

    /**
     * Requires an acknowledged claim before any package identity or assignment mutation
     */
    public function requireClaim(): void
    {
        if ($this->command === null || !$this->claimed) {
            throw new LogicException('A committed bootstrap claim is required.');
        }
    }

    /**
     * Validates exactly one invitation for the recorded operator, target and email
     */
    public function authorizeInvitation(InvitePendingUser $invitation): void
    {
        $this->requireClaim();
        if (
            $this->invited || $this->command === null
            || $invitation->getActorId() !== $this->command->input->actorId()
            || !$invitation->getUserId()->equals($this->command->userId)
            || $invitation->getEmail()->toString() !== $this->command->input->email->toString()
        ) {
            throw new LogicException('The invitation is outside this bootstrap claim.');
        }
        $this->invited = true;
    }

    /**
     * Validates exactly one hardcoded elevated assignment for the same recorded actor and target
     */
    public function authorizeAssignment(AssignRoleToUser $assignment): void
    {
        $this->requireClaim();
        if (
            !$this->invited || $this->assigned || $this->command === null
            || !$assignment->getActorId()->equals($this->command->input->operatorId)
            || !$assignment->getTargetUserId()->equals($this->command->userId)
            || $assignment->getRoleId()->toString() !== InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID
        ) {
            throw new LogicException('The assignment is outside this bootstrap claim.');
        }
        $this->assigned = true;
    }

    /**
     * Clears transient authority without reopening the durable claim
     */
    public function close(): void
    {
        $this->command = null;
        $this->claimed = false;
        $this->invited = false;
        $this->assigned = false;
    }
}
