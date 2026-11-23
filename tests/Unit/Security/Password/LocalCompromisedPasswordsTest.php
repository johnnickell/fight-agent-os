<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Password;

use App\Adapter\Security\Password\LocalCompromisedPasswords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verifies corpus interpretation and disclosure limits using inline bytes
 */
final class LocalCompromisedPasswordsTest extends TestCase
{
    /**
     * Folds ASCII case without trimming or changing Unicode code points
     */
    public function testOnlyAsciiCaseFolds(): void
    {
        $checker = self::checker(hash('sha256', 'codeé')."\n", 1);
        self::assertTrue($checker->isCompromised('CODEé'));
        self::assertFalse($checker->isCompromised('CODEÉ'));
        self::assertFalse($checker->isCompromised(' CODEé '));
    }

    /**
     * Rejects ill-formed or changed corpus bytes before accepting lookups
     */
    #[DataProvider('invalidCorpora')]
    public function testInvalidCorpus(string $data, int $count, ?string $checksum): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Password corpus integrity failed.');
        self::checker($data, $count, $checksum);
    }

    /**
     * Supplies independent checksum and representation failures
     *
     * @return iterable<string, array{string, int, ?string}>
     */
    public static function invalidCorpora(): iterable
    {
        $entry = str_repeat('a', 64);
        yield 'changed bytes' => [$entry."\n", 1, str_repeat('0', 64)];
        yield 'missing terminator' => [$entry, 1, null];
        yield 'duplicate entries' => [$entry."\n".$entry."\n", 2, null];
    }

    /**
     * Keeps raw and packed fingerprints out of diagnostics and serialization
     */
    public function testDiagnosticsProtectFingerprints(): void
    {
        $entry = hash('sha256', 'known fixture');
        $checker = self::checker($entry."\n", 1);
        self::assertSame(['corpus' => 'verified local data'], $checker->__debugInfo());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Password corpus serialization is disabled.');
        serialize($checker);
    }

    /**
     * Uses an in-memory PHP stream rather than installing a corpus file
     */
    private static function checker(string $data, int $count, ?string $checksum = null): LocalCompromisedPasswords
    {
        return new LocalCompromisedPasswords(
            'data://text/plain;base64,'.base64_encode($data),
            $checksum ?? hash('sha256', $data),
            $count
        );
    }
}
