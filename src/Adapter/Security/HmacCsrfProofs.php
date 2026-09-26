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

        $parts = parse_url($origin);
        if (
            $parts === false || !isset($parts['scheme'], $parts['host'])
            || $parts['scheme'] !== 'https'
            || preg_match('/\A[a-z0-9.-]+\z/D', $parts['host']) !== 1
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
            || $origin !== 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
        ) {
            throw new InvalidArgumentException('Invalid browser origin configuration.');
        }

        $this->key = (string) hex2bin($hexKey);
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
}
