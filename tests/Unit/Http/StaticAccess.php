<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\Authenticated;

/**
 * Class StaticAccess
 *
 * Declares a static Action candidate
 */
#[Authenticated]
final class StaticAccess
{
    /**
     * Returns no result at the declaration boundary
     */
    public static function handle(): void
    {
    }
}
