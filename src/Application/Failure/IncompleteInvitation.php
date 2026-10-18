<?php

declare(strict_types=1);

namespace App\Application\Failure;

use App\Application\Invitation\InvitationIssuance;
use RuntimeException;

/**
 * Class IncompleteInvitation
 *
 * Retains only safe inspected state after a possibly committed invitation step
 */
final class IncompleteInvitation extends RuntimeException
{
    /**
     * Constructs IncompleteInvitation
     */
    public function __construct(public readonly InvitationIssuance $outcome)
    {
        parent::__construct('Invitation completion is unconfirmed. Do not reinvite automatically.');
    }
}
