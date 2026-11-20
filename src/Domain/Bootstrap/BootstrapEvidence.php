<?php

declare(strict_types=1);

namespace App\Domain\Bootstrap;

use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class BootstrapEvidence
 *
 * Distinguishes a trusted bootstrap operator from an authenticated or pending target
 */
final readonly class BootstrapEvidence
{
    /**
     * Constructs BootstrapEvidence
     *
     * Captures the operator and target of a verified bootstrap
     */
    private function __construct(public string $actorId, public UserId $userId)
    {
    }

    /**
     * Records verified bootstrap membership in a separate mandatory completion transaction
     */
    public static function completed(BootstrapSuperAdmin $command): self
    {
        return new self('bootstrap:'.$command->input->operatorId, $command->userId);
    }
}
