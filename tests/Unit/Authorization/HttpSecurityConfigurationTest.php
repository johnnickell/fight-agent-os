<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Adapter\Security\HttpSecurityConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects the compatibility rules for HTTP security controls
 */
final class HttpSecurityConfigurationTest extends TestCase
{
    /**
     * Accepts production only with explicit controls and canonical links
     */
    public function testAcceptsExplicitProductionPolicy(): void
    {
        $environment = self::productionEnvironment();
        $inputs = HttpSecurityConfiguration::inputs();
        self::assertEqualsCanonicalizing(array_keys($environment), $inputs);

        $policy = new HttpSecurityConfiguration(array_intersect_key($environment, array_flip($inputs)));

        self::assertTrue($policy->production);
    }

    /**
     * Does not require a delivery key when delivery is not configured in local test mode
     */
    public function testLocalTestModeAllowsDisabledDelivery(): void
    {
        $environment = [
            'APP_ENV'                     => 'test',
            'APP_AUTH_TRANSPORT'          => false,
            'APP_AUTH_TRUSTED_PROXIES'    => false,
            'APP_CREDENTIAL_DELIVERY_KEY' => false
        ] + self::productionEnvironment();

        self::assertFalse((new HttpSecurityConfiguration($environment))->production);
    }

    /**
     * Refuses incompatible controls without echoing the private input values
     *
     * @param array<string, string|false> $overrides
     */
    #[DataProvider('incompatibleControls')]
    public function testRejectsIncompatibleControls(array $overrides, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new HttpSecurityConfiguration($overrides + self::productionEnvironment());
    }

    /**
     * Supplies incompatible security policies at distinct admission boundaries
     *
     * @return iterable<string, array{array<string, string|false>, string}>
     */
    public static function incompatibleControls(): iterable
    {
        yield 'implicit production transport' => [
            ['APP_AUTH_TRANSPORT' => false], 'Invalid security transport configuration.'
        ];
        yield 'malformed control key' => [
            ['APP_CSRF_MAC_KEY' => 'not a key'], 'Invalid security key configuration.'
        ];
        yield 'insufficient key diversity' => [
            ['APP_CSRF_MAC_KEY' => str_repeat('2a', 32)], 'Invalid security key configuration.'
        ];
        yield 'delivery and signing key reuse' => [
            ['APP_CREDENTIAL_DELIVERY_KEY' => strtoupper(self::fixtureKey('signing'))],
            'Security controls require independent keys.'
        ];
        yield 'implicit production rate policy' => [
            ['APP_AUTH_ENDPOINT_LIMIT' => false], 'Missing required rate policy configuration.'
        ];
        yield 'wrong reset completion path' => [
            ['APP_PASSWORD_RESET_PATH' => '/reset-password'], 'Invalid canonical credential link configuration.'
        ];
        yield 'local catcher uses a different activation origin' => [
            [
                'APP_ENV'                         => 'test',
                'APP_CREDENTIAL_DELIVERY_ADAPTER' => 'catcher',
                'APP_ACTIVATION_ORIGIN'           => 'https://other.example.test'
            ],
            'Invalid canonical credential link configuration.'
        ];
    }

    /**
     * Supplies a complete explicit policy with synthetic keys and addresses
     *
     * @return array<string, string>
     */
    private static function productionEnvironment(): array
    {
        return [
            'APP_ENV'                           => 'production',
            'APP_AUTH_TRANSPORT'                => 'direct',
            'APP_AUTH_TRUSTED_PROXIES'          => '[]',
            'APP_ACCESS_TOKEN_CONFIG'           => json_encode(
                ['keys' => [['secret_hex' => self::fixtureKey('signing')]]],
                JSON_THROW_ON_ERROR
            ),
            'APP_CSRF_MAC_KEY'                  => self::fixtureKey('csrf'),
            'APP_AUTH_RATE_KEY'                 => self::fixtureKey('auth rate'),
            'APP_ACTIVATION_RATE_KEY'           => self::fixtureKey('activation rate'),
            'APP_CREDENTIAL_DELIVERY_KEY'       => self::fixtureKey('delivery'),
            'APP_CREDENTIAL_DELIVERY_ADAPTER'   => 'disabled',
            'APP_BROWSER_ORIGIN'                => 'https://app.example.test',
            'APP_ACTIVATION_ORIGIN'             => 'https://app.example.test',
            'APP_ACTIVATION_PATH'               => '/activate',
            'APP_PASSWORD_RESET_ORIGIN'         => 'https://app.example.test',
            'APP_PASSWORD_RESET_PATH'           => '/reset-password/complete',
            'APP_AUTH_NETWORK_LIMIT'            => '5',
            'APP_AUTH_ENDPOINT_LIMIT'           => '5',
            'APP_INVITATION_RATE_WINDOW'        => '60',
            'APP_INVITATION_RATE_ISSUE_LIMIT'   => '5',
            'APP_INVITATION_RATE_STATUS_LIMIT'  => '5',
            'APP_INVITATION_RATE_CAPACITY'      => '50',
            'APP_INVITATION_RATE_RETRY_LIMIT'   => '5',
            'APP_INVITATION_RATE_RESEND_LIMIT'  => '5',
            'APP_INVITATION_RATE_CORRECT_LIMIT' => '5'
        ];
    }

    /**
     * Generates reproducible test data that must never be used as a real key
     */
    private static function fixtureKey(string $label): string
    {
        return hash('sha256', 'public HTTP security test fixture: '.$label);
    }
}
