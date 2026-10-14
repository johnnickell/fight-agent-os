<?php

declare(strict_types=1);

namespace App\Adapter\Http\Attribute;

use Attribute;

/**
 * Class RequiresPermission
 *
 * Declares conjunctive current catalog permissions on an authenticated Action
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class RequiresPermission
{
    /**
     * Constructs RequiresPermission
     */
    public function __construct(public string $name)
    {
    }
}
