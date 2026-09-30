<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\JsonBody;

/**
 * Class MissingValidation
 *
 * Declares an invalid source for failure checks
 */
final class MissingValidation
{
    /**
     * Handles input
     */
    #[JsonBody]
    public function __invoke(): void
    {
    }
}
