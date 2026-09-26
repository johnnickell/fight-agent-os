<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Adapter\Security\HmacCsrfProofs;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class HmacCsrfProofsTest
 *
 * Exercises the transport-free proof signing and verification boundary
 */
final class HmacCsrfProofsTest extends TestCase
{
    /**
     * Accepts a proof bound to the exact nonce, origin and deadline
     */
    public function test_that_bound_proof_is_accepted(): void
    {
        $signer = new HmacCsrfProofs(bin2hex(random_bytes(32)), 'https://agent-os.test');
        $nonce = bin2hex(random_bytes(32));
        $proof = $signer->sign($nonce, 1234567890 + 900);

        $valid = $signer->verify($nonce, $proof, 1234567890);

        self::assertTrue($valid);
    }

    /**
     * Lists distinct tampering and expiry boundaries
     *
     * @return iterable<string, array{string}>
     */
    public static function invalid_proof_cases(): iterable
    {
        yield 'different nonce' => ['nonce'];
        yield 'different origin' => ['origin'];
        yield 'different key' => ['key'];
        yield 'expired' => ['expired'];
        yield 'future deadline' => ['future'];
        yield 'tampered signature' => ['signature'];
        yield 'malformed nonce' => ['malformed'];
    }

    /**
     * Rejects each unsafe proof without treating it as a valid browser credential
     */
    #[DataProvider('invalid_proof_cases')]
    public function test_that_invalid_proof_is_rejected(string $case): void
    {
        $key = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $now = 1234567890;
        $signer = new HmacCsrfProofs($key, 'https://agent-os.test');
        $proof = $signer->sign($nonce, $now + ($case === 'future' ? 901 : 900));
        $nonce = match ($case) {
            'nonce' => bin2hex(random_bytes(32)),
            'malformed' => 'invalid',
            default => $nonce
        };
        $signer = match ($case) {
            'origin' => new HmacCsrfProofs($key, 'https://other.test'),
            'key' => new HmacCsrfProofs(bin2hex(random_bytes(32)), 'https://agent-os.test'),
            default => $signer
        };
        $proof = $case === 'signature' ? $proof.'x' : $proof;
        $now = $case === 'expired' ? $now + 900 : $now;

        $valid = $signer->verify($nonce, $proof, $now);

        self::assertFalse($valid);
    }

    /**
     * Lists invalid signing inputs
     *
     * @return iterable<string, array{string, int}>
     */
    public static function invalid_signing_inputs(): iterable
    {
        yield 'invalid nonce' => ['invalid', 1234567890];
        yield 'zero deadline' => [str_repeat('a', 64), 0];
        yield 'oversized deadline' => [str_repeat('a', 64), 10000000000];
    }

    /**
     * Refuses to sign malformed proof input
     */
    #[DataProvider('invalid_signing_inputs')]
    public function test_that_invalid_signing_input_is_rejected(string $nonce, int $expiresAt): void
    {
        $signer = new HmacCsrfProofs(bin2hex(random_bytes(32)), 'https://agent-os.test');
        $this->expectException(InvalidArgumentException::class);

        $signer->sign($nonce, $expiresAt);
    }

    /**
     * Lists invalid independent key and browser-origin configurations
     *
     * @return iterable<string, array{string, string}>
     */
    public static function invalid_configurations(): iterable
    {
        yield 'missing key' => ['', 'https://agent-os.test'];
        yield 'weak key' => [str_repeat('a', 62), 'https://agent-os.test'];
        yield 'invalid key encoding' => [str_repeat('z', 64), 'https://agent-os.test'];
        yield 'insecure origin' => [str_repeat('a', 64), 'http://agent-os.test'];
        yield 'origin path' => [str_repeat('a', 64), 'https://agent-os.test/path'];
        yield 'origin user info' => [str_repeat('a', 64), 'https://user@agent-os.test'];
    }

    /**
     * Refuses insecure key or origin configuration
     */
    #[DataProvider('invalid_configurations')]
    public function test_that_invalid_configuration_is_rejected(string $key, string $origin): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HmacCsrfProofs($key, $origin);
    }
}
