<?php

declare(strict_types=1);

namespace App\Application\Failure;

use RuntimeException;

/**
 * Class StateConflict
 *
 * Marks an explicit stale or conflicting use-case state for HTTP classification
 */
final class StateConflict extends RuntimeException
{
}
