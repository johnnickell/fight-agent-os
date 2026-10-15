<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Password;

use App\Adapter\Security\Password\Argon2idPasswords;
use PHPUnit\Framework\TestCase;

/**
 * Proves explicit hashing, random salts, exact-byte verification and rehash policy
 */
final class Argon2idPasswordsTest extends TestCase
{
    /**
     * Uses the selected parameters with fresh platform salts for every password
     */
    public function test_hashing_and_verification(): void
    {
        $passwords = new Argon2idPasswords();
        $candidate = " Distinct-Ωé42!e\u{0301} ";
        $first = $passwords->hash($candidate);
        $second = $passwords->hash($candidate);
        self::assertNotSame($first, $second);
        self::assertSame('argon2id', password_get_info($first)['algoName']);
        self::assertSame(Argon2idPasswords::OPTIONS, password_get_info($first)['options']);
        self::assertTrue($passwords->validate($candidate, $first));
        self::assertTrue($passwords->validate($candidate, $second));
        self::assertFalse($passwords->validate($candidate.'x', $first));
        self::assertFalse($passwords->validate(trim($candidate), $first));
        self::assertFalse($passwords->validate(" Distinct-Ωé42!é ", $first));
        self::assertFalse($passwords->needsRehash($first));
        self::assertFalse($passwords->validate($candidate, 'malformed'));
        self::assertTrue($passwords->needsRehash('malformed'));
    }

    /**
     * Requests migration for older parameters and algorithms without enforcing setting policy on verification
     */
    public function test_rehash_migration(): void
    {
        $passwords = new Argon2idPasswords();
        $oldArgon = password_hash('legacy', PASSWORD_ARGON2ID, [
            'memory_cost' => 8192,
            'time_cost'   => 1,
            'threads'     => 1
        ]);
        $oldBcrypt = password_hash('legacy', PASSWORD_BCRYPT, ['cost' => 4]);
        self::assertTrue($passwords->validate('legacy', $oldArgon));
        self::assertTrue($passwords->needsRehash($oldArgon));
        self::assertTrue($passwords->validate('legacy', $oldBcrypt));
        self::assertTrue($passwords->needsRehash($oldBcrypt));
        $current = $passwords->hash('legacy');
        self::assertTrue($passwords->validate('legacy', $current));
        self::assertFalse($passwords->needsRehash($current));
    }

    /**
     * Preserves null bytes rather than truncating valid Unicode input
     */
    public function test_null_byte_is_not_truncated(): void
    {
        $passwords = new Argon2idPasswords();
        $candidate = "Distinct42!\0suffix";
        $hash = $passwords->hash($candidate);
        self::assertTrue($passwords->validate($candidate, $hash));
        self::assertFalse($passwords->validate('Distinct42!', $hash));
    }
}
