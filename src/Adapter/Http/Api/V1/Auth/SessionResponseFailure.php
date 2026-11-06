<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

use RuntimeException;

/**
 * Class SessionResponseFailure
 *
 * Preserves required session-cookie deletion when local response rendering cannot complete
 */
final class SessionResponseFailure extends RuntimeException
{
    /**
     * Constructs SessionResponseFailure
     */
    public function __construct()
    {
        parent::__construct('Unable to render safe HTTP error.');
    }
}
