<?php

declare(strict_types=1);

namespace Tests\Unit\CredentialDelivery;

use App\Adapter\CredentialDelivery\SodiumCredentialDeliveryCipher;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\EncryptedCredentialMaterial;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Proves authenticated recoverable material remains purpose separated and redacted
 */
final class SodiumCredentialDeliveryCipherTest extends TestCase
{
    /**
     * Verifies random ciphertext, round trip and purpose separation
     */
    public function testRoundTripAndPurposeSeparation(): void
    {
        $key = bin2hex(random_bytes(32));
        $credential = bin2hex(random_bytes(32));
        $invitation = new SodiumCredentialDeliveryCipher($key, 'activation');
        $reset = new SodiumCredentialDeliveryCipher($key, 'password_reset');
        $first = $invitation->encrypt($credential);
        $second = $invitation->encrypt($credential);
        self::assertNotSame($first, $second);
        self::assertStringNotContainsString($credential, $first);
        self::assertSame($credential, $invitation->decrypt(EncryptedCredentialMaterial::fromString($first)));
        self::assertSame($credential, $invitation->decrypt(EncryptedCredentialMaterial::fromString($second)));
        $this->expectException(InvalidArgumentException::class);
        $reset->decrypt(EncryptedCredentialMaterial::fromString($first));
    }

    /**
     * Rejects malformed and modified ciphertext without exposing the material
     */
    public function testRejectsTamperedMaterial(): void
    {
        $cipher = new SodiumCredentialDeliveryCipher(bin2hex(random_bytes(32)), 'activation');
        $material = $cipher->encrypt(bin2hex(random_bytes(32)));
        $bytes = base64_decode(substr($material, 3), true);
        self::assertIsString($bytes);
        $bytes[30] = chr(ord($bytes[30]) ^ 1);
        foreach (['old:'.$material, 'v1:?', 'v1:'.base64_encode($bytes)] as $invalid) {
            try {
                $cipher->decrypt(EncryptedCredentialMaterial::fromString($invalid));
                self::fail('Unauthenticated material must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid credential delivery material.', $exception->getMessage());
            }
        }
    }

    /**
     * Refuses to persist an empty credential as recoverable material
     */
    public function testRejectsEmptyCredential(): void
    {
        $cipher = new SodiumCredentialDeliveryCipher(str_repeat('ab', 32), 'activation');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credential material must not be empty.');
        $cipher->encrypt('');
    }

    /**
     * Rejects missing keys and redacts secrets in ordinary diagnostics
     */
    public function testRequiresIndependentKeyAndRedactsDiagnostics(): void
    {
        foreach (['', bin2hex(random_bytes(16))] as $key) {
            try {
                new SodiumCredentialDeliveryCipher($key, 'activation');
                self::fail('Weak keys must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid credential delivery cipher configuration.', $exception->getMessage());
            }
        }
        $key = bin2hex(random_bytes(32));
        $cipher = new SodiumCredentialDeliveryCipher($key, 'activation');
        $decoded = hex2bin($key);
        self::assertIsString($decoded);
        $derived = hash_hkdf('sha256', $decoded, 32, 'fight-agent-os:credential-delivery:activation');
        $diagnostics = [var_export($cipher, true), print_r($cipher, true), var_export($cipher->__debugInfo(), true)];
        foreach ($diagnostics as $diagnostic) {
            self::assertStringNotContainsString($key, $diagnostic);
            self::assertStringNotContainsString($derived, $diagnostic);
        }
        $this->expectException(LogicException::class);
        serialize($cipher);
    }
}
