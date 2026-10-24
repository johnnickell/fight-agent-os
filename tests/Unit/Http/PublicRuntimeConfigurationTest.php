<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Web\PublicRuntimeConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Proves independent diagnostics and exact fail-closed environment eligibility
 */
final class PublicRuntimeConfigurationTest extends TestCase
{
    /**
     * Keeps eligibility separate from optional diagnostics
     */
    #[DataProvider('eligibility')]
    public function testEligibility(string|false $environment, string|false $allowed, bool $expected): void
    {
        foreach ([false, 'false', 'true'] as $debug) {
            if ($debug === 'true' && in_array($environment, ['prod', 'production'], true)) {
                continue;
            }
            $config = PublicRuntimeConfiguration::parse($environment, $debug, $allowed);
            self::assertSame($expected, $config->swaggerAvailable);
            self::assertSame([
                'schema_version'    => 2,
                'api_base_path'     => '/api/v1',
                'app_debug'         => $debug === 'true' && is_string($environment)
                    && preg_match('/\A[a-z][a-z0-9_-]{0,31}\z/D', $environment) === 1,
                'swagger_available' => $expected
            ], $config->toArray());
        }
    }

    /**
     * Supplies exact-list admission and independently derived refusal cases
     *
     * @return iterable<string, array{string|false, string|false, bool}>
     */
    public static function eligibility(): iterable
    {
        foreach (['local', 'test', 'staging', 'prod', 'production'] as $name) {
            yield 'opt in '.$name => [$name, json_encode([$name], JSON_THROW_ON_ERROR), true];
            yield 'empty '.$name => [$name, '[]', false];
        }
        yield 'legacy unlisted' => ['development', '["local"]', false];
        yield 'unlisted production' => ['production', '["local"]', false];
        yield 'missing environment' => [false, '["local"]', false];
        yield 'blank environment' => ['', '[""]', false];
        yield 'case sensitive' => ['Local', '["local"]', false];
        yield 'substring' => ['local_test', '["local"]', false];
        yield 'missing list' => ['local', false, false];
        yield 'malformed' => ['local', '[', false];
        yield 'scalar' => ['local', '"local"', false];
        yield 'object' => ['local', '{"0":"local"}', false];
        yield 'wildcard' => ['local', '["local","*"]', false];
        yield 'wrong type' => ['local', '["local",true]', false];
        yield 'duplicate' => ['local', '["local","local"]', false];
        yield 'invalid name' => ['local', '["local","staging.example"]', false];
    }

    /**
     * Rejects ambiguous diagnostics and production debug with fixed non-sensitive errors
     */
    #[DataProvider('invalidDiagnostics')]
    public function testInvalidDiagnostics(string $environment, string $debug, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        PublicRuntimeConfiguration::parse($environment, $debug, '["local","production"]');
    }

    /**
     * Supplies strict boolean and hard production refusals
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidDiagnostics(): iterable
    {
        foreach (['', '1', '0', 'TRUE', 'False', 'yes', ' false ', 'private-value'] as $value) {
            yield $value => ['local', $value, 'Invalid application diagnostic configuration.'];
        }
        foreach (['prod', 'production'] as $name) {
            yield $name => [$name, 'true', 'Application diagnostics are prohibited in production.'];
        }
    }
}
