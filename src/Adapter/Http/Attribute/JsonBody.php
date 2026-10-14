<?php

declare(strict_types=1);

namespace App\Adapter\Http\Attribute;

use Attribute;

/**
 * Class JsonBody
 *
 * Selects a JSON object as the Action's sole declared input source
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class JsonBody
{
}
