<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\RequiresPermission;
use LogicException;

/**
 * Class PermissionDeclaration
 *
 * Declares conjunction without allowing metadata inspection to construct it
 */
#[RequiresPermission('READ_USERS')]
#[RequiresPermission('READ_ROLES')]
final class PermissionDeclaration
{
    /**
     * Constructs PermissionDeclaration
     */
    public function __construct()
    {
        throw new LogicException('Construction is forbidden.');
    }

    /**
     * Returns no result at the declaration boundary
     */
    public function handle(): void
    {
    }
}
