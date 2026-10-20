<?php

declare(strict_types=1);

namespace App\Adapter\Security;

use App\Application\Security\Csrf\Service\CsrfProofs;
use InvalidArgumentException;

/**
 * Class HmacCsrfProofs
 *
 * Binds CSRF proof expiry and nonce to one trusted origin with an independent MAC key
 */
final readonly class HmacCsrfProofs implements CsrfProofs
{
    private string $key;
    private string $origin;

    /**
     * Constructs HmacCsrfProofs
     */
    public function __construct(string $hexKey, string $origin)
    {
        if (!preg_match('/\A[0-9a-f]{64,}\z/D', $hexKey) || strlen($hexKey) % 2 !== 0) {
            throw new InvalidArgumentException('Invalid CSRF MAC key configuration.');
        }

        $origin = self::normalizeOrigin($origin);
        if ($origin === null) {
            throw new InvalidArgumentException('Invalid browser origin configuration.');
        }

        $key = (string) hex2bin($hexKey);
        if (count(array_unique(str_split($key))) < 16) {
            throw new InvalidArgumentException('Invalid CSRF MAC key configuration.');
        }
        $this->key = $key;
        $this->origin = $origin;
    }

    /**
     * Checks the canonical encoding of a 256-bit nonce
     */
    public static function validNonce(string $nonce): bool
    {
        return preg_match('/\A[0-9a-f]{64}\z/D', $nonce) === 1;
    }

    /**
     * Returns the configured exact browser origin
     */
    public function origin(): string
    {
        return $this->origin;
    }

    /**
     * Checks a parsed HTTPS origin for the same host and effective port
     */
    public function acceptsOrigin(string $origin): bool
    {
        return self::normalizeOrigin($origin) === $this->origin;
    }

    /**
     * @inheritDoc
     */
    public function sign(string $nonce, int $expiresAt): string
    {
        if (!self::validNonce($nonce) || $expiresAt < 1 || $expiresAt > 9999999999) {
            throw new InvalidArgumentException('Invalid CSRF proof input.');
        }

        $deadline = (string) $expiresAt;

        return $deadline.'.'.hash_hmac('sha256', $nonce.'|'.$this->origin.'|'.$deadline, $this->key);
    }

    /**
     * @inheritDoc
     */
    public function verify(string $nonce, string $proof, int $now): bool
    {
        if (
            !self::validNonce($nonce)
            || preg_match('/\A([1-9][0-9]{0,9})\.([0-9a-f]{64})\z/D', $proof, $matches) !== 1
        ) {
            return false;
        }

        $expiresAt = (int) $matches[1];
        if ($expiresAt <= $now || $expiresAt > $now + 900) {
            return false;
        }

        return hash_equals($this->sign($nonce, $expiresAt), $proof);
    }

    /**
     * Normalizes bounded HTTPS origins without paths, suffix matching or authority ambiguity
     */
    private static function normalizeOrigin(string $origin): ?string
    {
        if (
            strlen($origin) > 300
            || preg_match('/\Ahttps:\/\/([a-zA-Z0-9.-]+)(?::([1-9][0-9]{0,4}))?\z/D', $origin, $match) !== 1
        ) {
            return null;
        }
        $host = strtolower($match[1]);
        $port = isset($match[2]) ? (int) $match[2] : 443;
        if (
            $port > 65535 || str_ends_with($host, '.')
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        ) {
            return null;
        }

        return 'https://'.$host.($port === 443 ? '' : ':'.$port);
    }

    /**
     * Returns only public origin metadata to diagnostic snapshots
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['origin' => $this->origin, 'key' => '[redacted]'];
    }
}
