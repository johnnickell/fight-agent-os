<?php

declare(strict_types=1);

namespace App\Application\Security\Activation;

use RuntimeException;

/**
 * Class ActivationUnavailable
 *
 * Discards infrastructure causes that may carry credential-bearing arguments
 */
final class ActivationUnavailable extends RuntimeException
{
}
