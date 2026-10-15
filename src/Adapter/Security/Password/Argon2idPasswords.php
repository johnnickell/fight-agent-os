<?php

declare(strict_types=1);

namespace App\Adapter\Security\Password;

use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * Class Argon2idPasswords
 *
 * Creates and verifies hashes with explicit Argon2id parameters and platform salts
 */
final class Argon2idPasswords implements PasswordHasher, PasswordValidator
{
    // OWASP Password Storage Cheat Sheet: Argon2id minimum 19 MiB, two iterations, one lane.
    public const array OPTIONS = ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1];

    /**
     * Constructs Argon2idPasswords
     */
    public function __construct()
    {
        try {
            if (!in_array('argon2id', password_algos(), true)) {
                throw new RuntimeException();
            }
            // Validate actual platform operation at startup, not just the algorithm name.
            $probe = random_bytes(32);
            $hash = $this->hash($probe);
            if (!$this->validate($probe, $hash) || $this->needsRehash($hash)) {
                throw new RuntimeException();
            }
        } catch (Throwable) {
            throw new RuntimeException('Argon2id password support is unavailable.');
        }
    }

    /**
     * @inheritDoc
     */
    public function hash(#[SensitiveParameter] string $password): string
    {
        try {
            return password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);
        } catch (Throwable) {
            throw new RuntimeException('Password hashing failed.');
        }
    }

    /**
     * @inheritDoc
     */
    public function validate(#[SensitiveParameter] string $password, #[SensitiveParameter] string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * @inheritDoc
     */
    public function needsRehash(#[SensitiveParameter] string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }
}
