<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\Authenticated;

/**
 * Class InvalidMarker
 *
 * Declares an invalid marker argument
 */
#[Authenticated('invalid')] // @phpstan-ignore attribute.noConstructor (Adversarial metadata input)
/**
 * Class InvalidMarker
 */
final class InvalidMarker
{
    /**
     * Returns no result at the declaration boundary
     */
    public function handle(): void
    {
    }
}
