<?php

declare(strict_types=1);

namespace App\Application\Failure;

use RuntimeException;

/**
 * Class ResourceNotFound
 *
 * Marks an explicitly authorized resource miss for HTTP classification
 */
final class ResourceNotFound extends RuntimeException
{
}
