<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

/**
 * Class MissingAccess
 *
 * Declares no access metadata
 */
final class MissingAccess
{
    /**
     * Returns no result at the declaration boundary
     */
    public function handle(): void
    {
    }
}
