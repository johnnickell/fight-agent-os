<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Security;

use App\Adapter\Security\AccessToken\AccessKeyring;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Verifies that ambiguous or weak signing authority fails closed
 */
final class AccessKeyringTest extends TestCase
{
    /**
     * Rejects invalid signing configuration without exposing its details
     *
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('invalidConfiguration')]
    public function testInvalidConfiguration(array $overrides): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid access-token configuration.');

        $this->keyring($overrides);
    }

    /**
     * Supplies independently invalid shapes, origins, and authority boundaries
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfiguration(): iterable
    {
        $active = self::active();
        yield 'unsupported algorithm' => [['algorithm' => 'none']];
        yield 'issuer with user info' => [['issuer' => 'https://example.test@invalid.test']];
        yield 'malformed key entry' => [['keys' => ['active']]];
        yield 'non-diverse secret' => [['keys' => [['kid' => 'active', 'secret_hex' => str_repeat('ab', 32)]]]];
        yield 'active key with historical fields' => [['keys' => [$active + ['last_issued_at' => 1]]]];
        yield 'historical key without bounds' => [['keys' => [
            $active, ['kid' => 'old', 'secret_hex' => $active['secret_hex']]
        ]]];
        yield 'duplicate signing material' => [['keys' => [$active, [
            'kid'            => 'old',
            'secret_hex'     => $active['secret_hex'],
            'last_issued_at' => 1,
            'verify_until'   => 901
        ]]]];
        yield 'missing active key' => [['active_kid' => 'missing', 'keys' => [$active + [
            'last_issued_at' => 1,
            'verify_until'   => 901
        ]]]];
    }

    /**
     * Hides literal key material from routine value dumps
     */
    public function testDebugOutput(): void
    {
        self::assertSame(['configuration' => '[redacted]'], $this->keyring([])->__debugInfo());
    }

    /**
     * Returns a synthetic signing fixture, never a deployed secret
     *
     * @return array{kid: string, secret_hex: string}
     */
    private static function active(): array
    {
        return ['kid' => 'active', 'secret_hex' => bin2hex(implode('', array_map(chr(...), range(0, 31))))];
    }

    /**
     * Constructs the keyring with a deterministic time source
     *
     * @param array<string, mixed> $overrides
     */
    private function keyring(array $overrides): AccessKeyring
    {
        return new AccessKeyring(json_encode(array_replace([
            'algorithm'  => 'HS256',
            'issuer'     => 'https://example.test',
            'audience'   => 'agent-os',
            'active_kid' => 'active',
            'keys'       => [self::active()]
        ], $overrides), JSON_THROW_ON_ERROR), new AccessClock());
    }
}
