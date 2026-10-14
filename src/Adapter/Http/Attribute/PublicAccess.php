<?php

declare(strict_types=1);

namespace App\Adapter\Http\Attribute;

use Attribute;

/**
 * Class PublicAccess
 *
 * Declares an approved public operation without bypassing its endpoint security controls
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PublicAccess
{
}
