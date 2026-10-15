<?php

declare(strict_types=1);

namespace App\Adapter\Security\Password;

use App\Application\Security\Password\CompromisedPasswordChecker;
use RuntimeException;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * Class LocalCompromisedPasswords
 *
 * Verifies and scans the local one-way common-password corpus
 */
final readonly class LocalCompromisedPasswords implements CompromisedPasswordChecker
{
    public const string CHECKSUM = '03e2cbdce73b1aac0d667fd2ba18899141c3456b1f7c82be333f39d3e939cc04';
    public const int COUNT = 12581;

    private SensitiveParameterValue $entries;

    /**
     * Constructs LocalCompromisedPasswords
     */
    public function __construct(
        string $path,
        string $expectedChecksum = self::CHECKSUM,
        int $expectedCount = self::COUNT
    ) {
        $data = @file_get_contents($path);
        if (
            $data === false
            || $expectedCount < 1
            || !hash_equals($expectedChecksum, hash('sha256', $data))
        ) {
            throw new RuntimeException('Password corpus integrity failed.');
        }

        $entries = explode("\n", $data);
        if (array_pop($entries) !== '' || count($entries) !== $expectedCount) {
            throw new RuntimeException('Password corpus integrity failed.');
        }

        $previous = '';
        foreach ($entries as $entry) {
            if (preg_match('/\A[0-9a-f]{64}\z/D', $entry) !== 1 || strcmp($previous, $entry) >= 0) {
                throw new RuntimeException('Password corpus integrity failed.');
            }
            $previous = $entry;
        }
        // Keep a compact verified snapshot rather than a high-overhead PHP array per container.
        $this->entries = new SensitiveParameterValue(pack('H*', implode('', $entries)));
    }

    /**
     * @inheritDoc
     */
    public function isCompromised(#[SensitiveParameter] string $candidate): bool
    {
        // Fold only ASCII letters; never trim, normalize or alter the password used for hashing.
        $lookup = hash('sha256', strtr($candidate, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), true);
        $found = 0;
        /** @var string $entries */
        $entries = $this->entries->getValue();
        $length = strlen($entries);
        for ($offset = 0; $offset < $length; $offset += 32) {
            // Visit every entry without revealing match position through early return.
            $found |= (int) hash_equals(substr($entries, $offset, 32), $lookup);
        }

        return $found !== 0;
    }

    /**
     * Hides lookup material from ordinary object dumps
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['corpus' => 'verified local data'];
    }

    /**
     * Prevents serialization of lookup material
     *
     * @return never
     */
    public function __serialize(): array
    {
        throw new RuntimeException('Password corpus serialization is disabled.');
    }
}
