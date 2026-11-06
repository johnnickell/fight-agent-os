<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Failure;

use Fight\Common\Application\Error\Exception\StateConflict;

/**
 * Class CurrentSessionRevocation
 *
 * Identifies the explicit public use_logout intent without exposing a diagnostic code
 */
final class CurrentSessionRevocation extends StateConflict
{
}
