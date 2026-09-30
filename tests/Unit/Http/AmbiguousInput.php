<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\QueryString;
use Fight\Common\Application\Attribute\Validation;

/**
 * Class AmbiguousInput
 *
 * Declares incompatible sources for rejection checks
 */
final class AmbiguousInput
{
    /**
     * Handles input
     */
    #[JsonBody]
    #[QueryString]
    #[Validation(rules: [])]
    public function __invoke(): void
    {
    }
}
