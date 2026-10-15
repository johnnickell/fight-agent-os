<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Class CanonicalActivationLink
 *
 * Builds credential-only fragment links from explicit canonical HTTPS configuration
 */
final readonly class CanonicalActivationLink
{
    private string $base;

    /**
     * Constructs CanonicalActivationLink
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
            throw new InvalidArgumentException('Invalid canonical activation URL configuration.');
        }
        $this->base = $origin.$path;
    }

    /**
     * Builds a link only for the provider invocation
     */
    public function forCredential(#[SensitiveParameter] string $credential): string
    {
        if ($credential === '') {
            throw new InvalidArgumentException('Missing activation material.');
        }

        return $this->base.'#credential='.rawurlencode($credential);
    }
}
