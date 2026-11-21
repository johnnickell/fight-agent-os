<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

use RuntimeException;

/**
 * Class PasswordMutationUnconfirmed
 *
 * Marks an unconfirmed response after a password mutation's success event failed to dispatch
 */
final class PasswordMutationUnconfirmed extends RuntimeException
{
    /**
     * Constructs PasswordMutationUnconfirmed
     */
    public function __construct()
    {
        parent::__construct('Password mutation event dispatch failed; do not replay the request.');
    }
}
