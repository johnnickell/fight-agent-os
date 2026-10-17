<?php

declare(strict_types=1);

namespace App\Application\Failure;

use RuntimeException;

/**
 * Class RateLimited
 *
 * Carries a bounded delay without exposing limiter keys
 */
final class RateLimited extends RuntimeException
{
    /**
     * Constructs RateLimited
     */
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Too many requests.');
    }
}
