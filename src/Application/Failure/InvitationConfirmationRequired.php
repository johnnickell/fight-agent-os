<?php

declare(strict_types=1);

namespace App\Application\Failure;

use Fight\Common\Application\Error\Exception\RuleViolation;

/**
 * Class InvitationConfirmationRequired
 *
 * Identifies deliberate elevation refusal for a fixed safe confirmation-field projection
 */
final class InvitationConfirmationRequired extends RuleViolation
{
}
