<?php

declare(strict_types=1);

namespace App\Adapter\Security\AccessToken;

use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use InvalidArgumentException;
use SensitiveParameter;
use Throwable;

/**
 * Class AccessKeyring
 *
 * Validates external signing authority without exposing key material
 */
final readonly class AccessKeyring
{
    public string $issuer;
    public string $audience;
    public string $activeKid;

    /** @var array<string, array{secret: string, last_issued_at: ?int, verify_until: ?int}> */
    private array $keys;

    /**
     * Constructs AccessKeyring
     */
    public function __construct(#[SensitiveParameter] string $configuration, Clock $clock)
    {
        try {
            $config = json_decode($configuration, true, 8, JSON_THROW_ON_ERROR);
            if (
                !is_array($config)
                || array_keys($config) !== ['algorithm', 'issuer', 'audience', 'active_kid', 'keys']
                || $config['algorithm'] !== 'HS256'
                || !is_string($config['issuer'])
                || !is_string($config['audience'])
                || !is_string($config['active_kid'])
                || !is_array($config['keys'])
                || !array_is_list($config['keys'])
                || count($config['keys']) < 1
                || count($config['keys']) > 2
            ) {
                throw new InvalidArgumentException();
            }

            $origin = parse_url($config['issuer']);
            if (
                strlen($config['issuer']) > 256
                || preg_match('/\Ahttps:\/\/[A-Za-z0-9.:\/-]+\z/D', $config['issuer']) !== 1
                || !is_array($origin)
                || ($origin['scheme'] ?? '') !== 'https'
                || empty($origin['host'])
                || preg_match('/\A[A-Za-z0-9][A-Za-z0-9:._\/-]{0,255}\z/D', $config['audience']) !== 1
            ) {
                throw new InvalidArgumentException();
            }

            $keys = [];
            foreach ($config['keys'] as $key) {
                if (
                    !is_array($key)
                    || !is_string($key['kid'] ?? null)
                    || preg_match('/\A[A-Za-z0-9_-]{1,64}\z/D', $key['kid']) !== 1
                    || isset($keys[$key['kid']])
                    || !is_string($key['secret_hex'] ?? null)
                    || preg_match('/\A(?:[a-f0-9]{2}){32,64}\z/D', $key['secret_hex']) !== 1
                ) {
                    throw new InvalidArgumentException();
                }

                $secret = hex2bin($key['secret_hex']);
                if ($secret === false || count(array_unique(str_split($secret))) < 16) {
                    throw new InvalidArgumentException();
                }

                $active = $key['kid'] === $config['active_kid'];
                if ($active) {
                    if (array_keys($key) !== ['kid', 'secret_hex']) {
                        throw new InvalidArgumentException();
                    }
                } elseif (
                    array_keys($key) !== ['kid', 'secret_hex', 'last_issued_at', 'verify_until']
                    || !is_int($key['last_issued_at'])
                    || !is_int($key['verify_until'])
                    || $key['last_issued_at'] < 1
                    || $key['last_issued_at'] > $clock->now()->getTimestamp()
                    || $key['last_issued_at'] > PHP_INT_MAX - 900
                    || $key['verify_until'] !== $key['last_issued_at'] + 900
                ) {
                    throw new InvalidArgumentException();
                }

                foreach ($keys as $existing) {
                    if (hash_equals($existing['secret'], $secret)) {
                        throw new InvalidArgumentException();
                    }
                }

                $keys[$key['kid']] = [
                    'secret'         => $secret,
                    'last_issued_at' => $active ? null : $key['last_issued_at'],
                    'verify_until'   => $active ? null : $key['verify_until']
                ];
            }

            if (!isset($keys[$config['active_kid']])) {
                throw new InvalidArgumentException();
            }

            $this->issuer = $config['issuer'];
            $this->audience = $config['audience'];
            $this->activeKid = $config['active_kid'];
            $this->keys = $keys;
        } catch (Throwable) {
            throw new InvalidArgumentException('Invalid access-token configuration.');
        }
    }

    /**
     * Creates signatures exclusively with the active key
     */
    public function sign(#[SensitiveParameter] string $message): string
    {
        return hash_hmac('sha256', $message, $this->keys[$this->activeKid]['secret'], true);
    }

    /**
     * Verifies only configured keys within their finite issuance and consumption bounds
     */
    public function verify(
        string $kid,
        #[SensitiveParameter] string $message,
        #[SensitiveParameter] string $signature,
        int $issuedAt,
        int $now
    ): bool {
        $key = $this->keys[$kid] ?? null;

        return $key !== null
            && ($key['verify_until'] === null || $now < $key['verify_until'])
            && ($key['last_issued_at'] === null || $issuedAt <= $key['last_issued_at'])
            && hash_equals(hash_hmac('sha256', $message, $key['secret'], true), $signature);
    }
}
