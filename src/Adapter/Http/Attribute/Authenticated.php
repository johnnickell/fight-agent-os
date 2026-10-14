<?php

declare(strict_types=1);

namespace App\Adapter\Http\Attribute;

use Attribute;

/**
 * Class Authenticated
 *
 * Declares current authenticated access without an additional permission prerequisite
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Authenticated
{
}
