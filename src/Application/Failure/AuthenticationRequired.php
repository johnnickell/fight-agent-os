<?php

declare(strict_types=1);

namespace App\Application\Failure;

use RuntimeException;

/**
 * Class AuthenticationRequired
 *
 * Marks a use-case authentication refusal for safe HTTP classification
 */
final class AuthenticationRequired extends RuntimeException
{
}
