<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Adapter\Security\HmacCsrfProofs;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the transport-free nonce proof and expiry contract
 */
final class HmacCsrfProofsTest extends TestCase
{
    /**
     * Verifies reuse and rejection of tampering, origin, nonce and deadlines
     */
    public function testBoundProofRequiresExactNonceOriginDeadlineAndMac(): void
    {
        $key = bin2hex(random_bytes(32));
        $signer = new HmacCsrfProofs($key, 'https://agent-os.test');
        $otherOrigin = new HmacCsrfProofs($key, 'https://other.test');
        $otherKey = new HmacCsrfProofs(bin2hex(random_bytes(32)), 'https://agent-os.test');
        $nonce = bin2hex(random_bytes(32));
        $now = 1234567890;
        $proof = $signer->sign($nonce, $now + 900);
        self::assertTrue($signer->verify($nonce, $proof, $now));
        self::assertTrue($signer->verify($nonce, $proof, $now + 899));
        self::assertFalse($signer->verify($nonce, $proof, $now + 900));
        self::assertFalse($signer->verify($nonce, $proof, $now - 1));
        self::assertFalse($signer->verify(bin2hex(random_bytes(32)), $proof, $now));
        self::assertFalse($otherOrigin->verify($nonce, $proof, $now));
        self::assertFalse($otherKey->verify($nonce, $proof, $now));
        self::assertFalse($signer->verify($nonce, $proof.'x', $now));
        self::assertFalse($signer->verify($nonce, '01.'.str_repeat('a', 64), $now));
        self::assertFalse($signer->verify('invalid', $proof, $now));
        self::assertFalse($signer->verify($nonce, $signer->sign($nonce, $now + 901), $now));
    }

    /**
     * Verifies invalid key and browser-origin inputs cannot create a signer
     */
    public function testInvalidConfigurationIsRejected(): void
    {
        $validKey = bin2hex(random_bytes(32));
        foreach (['', bin2hex(random_bytes(31)), str_repeat('z', 64)] as $key) {
            try {
                new HmacCsrfProofs($key, 'https://agent-os.test');
                self::fail('Expected invalid MAC key to fail.');
            } catch (InvalidArgumentException $error) {
                self::assertSame('Invalid CSRF MAC key configuration.', $error->getMessage());
            }
        }
        foreach (
            ['', 'http://agent-os.test', 'https://agent-os.test/path',
            'https://user@agent-os.test', 'https://agent-os.test/'] as $origin
        ) {
            try {
                new HmacCsrfProofs($validKey, $origin);
                self::fail('Expected invalid origin to fail.');
            } catch (InvalidArgumentException $error) {
                self::assertSame('Invalid browser origin configuration.', $error->getMessage());
            }
        }
    }
}
