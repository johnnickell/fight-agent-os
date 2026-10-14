<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\PublicAccess;

/**
 * Class UnapprovedPublic
 *
 * Declares an unapproved public operation
 */
#[PublicAccess]
final class UnapprovedPublic
{
    /**
     * Returns no result at the declaration boundary
     */
    public function handle(): void
    {
    }
}
