<?php

declare(strict_types=1);

namespace App\Application\Failure;

use RuntimeException;

/**
 * Class InvitationRecoveryUncertain
 *
 * Requires a safe status read after dispatch coordination failed without assuming rollback
 */
final class InvitationRecoveryUncertain extends RuntimeException
{
}
