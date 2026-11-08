<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Failure;

use Fight\Common\Application\Error\Exception\RuleViolation;

/**
 * Class ProjectInputFailure
 *
 * Identifies the adapter's fixed Project-field projection without changing Domain direction
 */
final class ProjectInputFailure extends RuleViolation
{
}
