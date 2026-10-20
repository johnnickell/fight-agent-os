<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use Fight\AccessControl\Domain\AccessControl\User\UserId;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Class CanonicalCredentialLink
 *
 * Builds fragment-only links from explicit canonical HTTPS configuration
 */
final readonly class CanonicalCredentialLink
{
    private string $base;

    /**
     * Constructs CanonicalCredentialLink
     */
    public function __construct(string $origin, string $path)
    {
        // Deliberately accept a small canonical grammar, not every URL a browser can interpret.
        $valid = preg_match(
            '~\Ahttps://(localhost|(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63})(?::([1-9][0-9]{0,4}))?\z~D',
            $origin,
            $parts
        );
        if (
            $valid !== 1 || strlen($origin) > 253 || (isset($parts[2]) && (int) $parts[2] > 65535)
            || preg_match('~\A/(?:[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*)?\z~D', $path) !== 1
        ) {
            throw new InvalidArgumentException('Invalid canonical credential URL configuration.');
        }
        $this->base = $origin.$path;
    }

    /**
     * Builds the existing invitation fragment only for the provider invocation
     */
    public function forCredential(
        #[SensitiveParameter] string $credential,
        UserId $userId
    ): string {
        if ($credential === '') {
            throw new InvalidArgumentException('Missing activation material.');
        }

        $identity = '&user_id='.rawurlencode($userId->toString());

        return $this->base.'#credential='.rawurlencode($credential).$identity;
    }

    /**
     * Builds the bounded reset fragment without interpreting opaque credential bytes
     */
    public function forPasswordReset(UserId $userId, #[SensitiveParameter] string $credential): string
    {
        if ($credential === '' || strlen($credential) > 4096) {
            throw new InvalidArgumentException('Invalid password-reset material.');
        }

        return $this->base.'#user_id='.$userId->toString().'&credential='.rawurlencode($credential);
    }
}
