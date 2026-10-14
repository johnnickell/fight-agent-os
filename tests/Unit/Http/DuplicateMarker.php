<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\Authenticated;

/**
 * Class DuplicateMarker
 *
 * Declares a nonrepeatable marker twice
 */
#[Authenticated]
#[Authenticated] // @phpstan-ignore attribute.nonRepeatable (Adversarial metadata input)
/**
 * Class DuplicateMarker
 */
final class DuplicateMarker
{
    /**
     * Returns no result at the declaration boundary
     */
    public function handle(): void
    {
    }
}
