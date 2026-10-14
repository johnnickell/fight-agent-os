<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\Authenticated;
use App\Adapter\Http\Attribute\RequiresPermission;

/**
 * Class ConflictingAccess
 *
 * Declares conflicting access modes
 */
#[Authenticated]
#[RequiresPermission('READ_USERS')]
final class ConflictingAccess
{
    /**
     * Returns no result at the declaration boundary
     */
    public function handle(): void
    {
    }
}
