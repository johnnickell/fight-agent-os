<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\Authenticated;

/**
 * Class NonFinalAccess
 *
 * Declares a nonfinal Action candidate
 */
#[Authenticated]
class NonFinalAccess
{
    /**
     * Returns no result at the declaration boundary
     */
    public function handle(): void
    {
    }
}
