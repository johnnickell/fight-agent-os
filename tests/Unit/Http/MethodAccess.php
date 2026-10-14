<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\Authenticated;

/**
 * Class MethodAccess
 *
 * Declares access on an unsupported method target
 */
#[Authenticated]
final class MethodAccess
{
    /**
     * Returns no result at the declaration boundary
     *
     * @phpstan-ignore attribute.target (Adversarial metadata input)
     */
    #[Authenticated]
    public function handle(): void
    {
    }
}
