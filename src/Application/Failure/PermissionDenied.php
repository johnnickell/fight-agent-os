<?php

declare(strict_types=1);

namespace App\Application\Failure;

use RuntimeException;

/**
 * Class PermissionDenied
 *
 * Marks an explicit permission refusal for safe HTTP classification
 */
final class PermissionDenied extends RuntimeException
{
}
