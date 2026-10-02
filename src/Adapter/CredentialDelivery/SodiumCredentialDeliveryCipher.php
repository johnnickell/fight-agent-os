<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetDeliveryCipher;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\EncryptedCredentialMaterial;
use InvalidArgumentException;
use LogicException;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * Class SodiumCredentialDeliveryCipher
 *
 * Encrypts recoverable credential material with a purpose-separated installation key
 */
final readonly class SodiumCredentialDeliveryCipher implements InvitationDeliveryCipher, PasswordResetDeliveryCipher
{
    private SensitiveParameterValue $key;

    /**
     * Constructs SodiumCredentialDeliveryCipher
     */
    public function __construct(#[SensitiveParameter] string $hexKey, private string $purpose)
    {
        $decoded = preg_match('/\A[0-9a-fA-F]{64}\z/D', $hexKey) ? hex2bin($hexKey) : false;
        if (
            $decoded === false || strlen($decoded) !== 32
            || !in_array($purpose, ['activation', 'password_reset'], true)
        ) {
            throw new InvalidArgumentException('Invalid credential delivery cipher configuration.');
        }

        $this->key = new SensitiveParameterValue(
            hash_hkdf('sha256', $decoded, 32, 'fight-agent-os:credential-delivery:'.$purpose)
        );
    }

    /**
     * Encrypts one credential with an independent random nonce
     */
    public function encrypt(#[SensitiveParameter] string $plaintext): string
    {
        if ($plaintext === '') {
            throw new InvalidArgumentException('Credential material must not be empty.');
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return 'v1:'.base64_encode($nonce.sodium_crypto_secretbox($plaintext, $nonce, $this->key->getValue()));
    }

    /**
     * Decrypts authenticated material for a committed live claim
     */
    public function decrypt(EncryptedCredentialMaterial $encryptedMaterial): string
    {
        $encoded = $encryptedMaterial->reveal();
        $bytes = str_starts_with($encoded, 'v1:') ? base64_decode(substr($encoded, 3), true) : false;
        if (
            $bytes === false
            || strlen($bytes) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES
        ) {
            throw new InvalidArgumentException('Invalid credential delivery material.');
        }

        $nonce = substr($bytes, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(
            substr($bytes, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $nonce,
            $this->key->getValue()
        );
        if ($plaintext === false || $plaintext === '') {
            throw new InvalidArgumentException('Invalid credential delivery material.');
        }

        return $plaintext;
    }

    /**
     * Redacts the installation key from diagnostics
     *
     * @return array{purpose: string, key: string}
     */
    public function __debugInfo(): array
    {
        return ['purpose' => $this->purpose, 'key' => '[REDACTED]'];
    }

    /**
     * Prevents persistence of the installation key
     *
     * @return never
     */
    public function __serialize(): array
    {
        throw new LogicException('Credential delivery cipher cannot be serialized.');
    }
}
