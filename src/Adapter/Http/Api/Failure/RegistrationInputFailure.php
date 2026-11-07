<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Failure;

use Fight\Common\Application\Error\Exception\RuleViolation;

/**
 * Class RegistrationInputFailure
 *
 * Identifies the adapter's fixed registration-field projection without changing Domain direction
 */
final class RegistrationInputFailure extends RuleViolation
{
}
