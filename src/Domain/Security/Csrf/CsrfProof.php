<?php

declare(strict_types=1);

namespace App\Domain\Security\Csrf;

use InvalidArgumentException;

/**
 * Class CsrfProof
 *
 * Binds a canonical proof to its expiry
 */
final readonly class CsrfProof
{
    /**
     * Constructs CsrfProof
     */
    public function __construct(public string $value, public int $expiresAt)
    {
        if (
            $expiresAt < 1 || $expiresAt > 9999999999
            || preg_match('/\A'.preg_quote((string) $expiresAt, '/').'\.[0-9a-f]{64}\z/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Invalid CSRF proof.');
        }
    }
}
