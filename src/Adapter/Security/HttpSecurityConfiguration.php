<?php

declare(strict_types=1);

namespace App\Adapter\Security;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Class HttpSecurityConfiguration
 *
 * Validates cross-control compatibility without retaining private environment values
 */
final readonly class HttpSecurityConfiguration
{
    public bool $production;

    /**
     * Constructs HttpSecurityConfiguration
     *
     * @param array<string, string|false> $environment
     */
    public function __construct(#[SensitiveParameter] array $environment)
    {
        $this->production = !in_array($environment['APP_ENV'], ['local', 'development', 'test'], true);
        if (
            ($this->production && $environment['APP_AUTH_TRANSPORT'] !== 'direct')
            || !in_array($environment['APP_AUTH_TRANSPORT'], [false, 'direct'], true)
            || !in_array($environment['APP_AUTH_TRUSTED_PROXIES'], [false, '[]'], true)
            || ($this->production && $environment['APP_AUTH_TRUSTED_PROXIES'] !== '[]')
        ) {
            throw new InvalidArgumentException('Invalid security transport configuration.');
        }
        $signing = json_decode($environment['APP_ACCESS_TOKEN_CONFIG'] ?: '{}', true);
        $keys = [];
        foreach (
            ['APP_CSRF_MAC_KEY', 'APP_AUTH_RATE_KEY', 'APP_ACTIVATION_RATE_KEY', 'APP_CREDENTIAL_DELIVERY_KEY'] as $name
        ) {
            $key = $environment[$name];
            if ($name === 'APP_CREDENTIAL_DELIVERY_KEY' && !$this->production && ($key === false || $key === '')) {
                continue;
            }
            if (
                !is_string($key) || preg_match('/\A(?:[a-fA-F0-9]{2}){32,64}\z/D', $key) !== 1
                || count(array_unique(str_split((string) hex2bin($key)))) < 16
            ) {
                throw new InvalidArgumentException('Invalid security key configuration.');
            }
            $keys[] = strtolower($key);
        }
        foreach ($signing['keys'] ?? [] as $entry) {
            $keys[] = strtolower($entry['secret_hex'] ?? '');
        }
        if (count(array_unique($keys)) !== count($keys)) {
            throw new InvalidArgumentException('Security controls require independent keys.');
        }
        if ($this->production) {
            foreach (self::limitInputs() as $name) {
                if (!is_string($environment[$name]) || $environment[$name] === '') {
                    throw new InvalidArgumentException('Missing required rate policy configuration.');
                }
            }
        }
        if ($this->production || $environment['APP_CREDENTIAL_DELIVERY_ADAPTER'] === 'catcher') {
            if (
                $environment['APP_ACTIVATION_ORIGIN'] !== $environment['APP_BROWSER_ORIGIN']
                || $environment['APP_PASSWORD_RESET_ORIGIN'] !== $environment['APP_BROWSER_ORIGIN']
                || $environment['APP_ACTIVATION_PATH'] !== '/activate'
                || $environment['APP_PASSWORD_RESET_PATH'] !== '/reset-password/complete'
            ) {
                throw new InvalidArgumentException('Invalid canonical credential link configuration.');
            }
        }
    }

    /**
     * Returns the closed set of external inputs consumed by this policy
     *
     * @return list<string>
     */
    public static function inputs(): array
    {
        return [...self::limitInputs(),
            'APP_ENV', 'APP_AUTH_TRANSPORT', 'APP_AUTH_TRUSTED_PROXIES', 'APP_ACCESS_TOKEN_CONFIG',
            'APP_CSRF_MAC_KEY', 'APP_AUTH_RATE_KEY', 'APP_ACTIVATION_RATE_KEY', 'APP_CREDENTIAL_DELIVERY_KEY',
            'APP_CREDENTIAL_DELIVERY_ADAPTER', 'APP_BROWSER_ORIGIN', 'APP_ACTIVATION_ORIGIN', 'APP_ACTIVATION_PATH',
            'APP_PASSWORD_RESET_ORIGIN', 'APP_PASSWORD_RESET_PATH'
        ];
    }

    /**
     * Returns configurable policies whose absence cannot select production defaults
     *
     * @return list<string>
     */
    private static function limitInputs(): array
    {
        return [
            'APP_AUTH_NETWORK_LIMIT', 'APP_AUTH_ENDPOINT_LIMIT', 'APP_INVITATION_RATE_WINDOW',
            'APP_INVITATION_RATE_ISSUE_LIMIT', 'APP_INVITATION_RATE_STATUS_LIMIT', 'APP_INVITATION_RATE_CAPACITY',
            'APP_INVITATION_RATE_RETRY_LIMIT', 'APP_INVITATION_RATE_RESEND_LIMIT', 'APP_INVITATION_RATE_CORRECT_LIMIT'
        ];
    }
}
