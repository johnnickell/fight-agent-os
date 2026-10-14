<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\RequiresPermission;

/**
 * Class InvalidPermission
 *
 * Declares a malformed permission name
 */
#[RequiresPermission('read_users')]
final class InvalidPermission
{
    /**
     * Returns no result at the declaration boundary
     */
    public function handle(): void
    {
    }
}
