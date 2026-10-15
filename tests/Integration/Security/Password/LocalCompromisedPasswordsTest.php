<?php

declare(strict_types=1);

namespace Tests\Integration\Security\Password;

use App\Adapter\Security\Password\LocalCompromisedPasswords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SensitiveParameter;

/**
 * Proves local corpus lookup, normalization and fail-closed integrity
 */
final class LocalCompromisedPasswordsTest extends TestCase
{
    private string $path;

    /**
     * Creates an isolated runtime fixture path
     */
    protected function setUp(): void
    {
        $this->path = dirname(__DIR__, 4).'/var/password-corpus-'.bin2hex(random_bytes(8));
    }

    /**
     * Removes only the owned fixture
     */
    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * Rejects pinned common fixtures regardless of ASCII letter case
     */
    public function test_pinned_corpus_lookup(): void
    {
        $checker = new LocalCompromisedPasswords(dirname(__DIR__, 4).'/resources/passwords/common.sha256');
        self::assertTrue($checker->isCompromised('password'));
        self::assertTrue($checker->isCompromised('PASSWORD'));
        self::assertTrue($checker->isCompromised('P@ssw0rd'));
        self::assertTrue($checker->isCompromised('p@SSW0RD'));
        self::assertFalse($checker->isCompromised('Distinct-Ωé42!'));
        self::assertFalse($checker->isCompromised(' P@ssw0rd '));
        self::assertFalse($checker->isCompromised('Ｐ@ssw0rd'));
        self::assertTrue($checker->isCompromised('P@ssw0rd'));
    }

    /**
     * Preserves Unicode normalization and whitespace distinctions
     */
    public function test_lookup_folds_only_ascii(): void
    {
        $known = 'Ab1!café';
        $data = hash('sha256', 'ab1!café')."\n";
        file_put_contents($this->path, $data);
        $checker = new LocalCompromisedPasswords($this->path, hash('sha256', $data), 1);
        self::assertTrue($checker->isCompromised($known));
        self::assertFalse($checker->isCompromised("Ab1!cafe\u{0301}"));
        self::assertFalse($checker->isCompromised('Ab1!cafÉ'));
        self::assertFalse($checker->isCompromised($known.' '));
    }

    /**
     * Fails closed on unavailable required data without filesystem warnings
     */
    public function test_missing_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Password corpus integrity failed.');
        new LocalCompromisedPasswords($this->path);
    }

    /**
     * Fails closed on a changed corpus even when its format is valid
     */
    public function test_wrong_checksum(): void
    {
        file_put_contents($this->path, str_repeat('a', 64)."\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Password corpus integrity failed.');
        new LocalCompromisedPasswords($this->path);
    }

    /**
     * Rejects malformed data independently of the transport checksum
     */
    #[DataProvider('malformed_corpora')]
    public function test_malformed_corpus(#[SensitiveParameter] string $data, int $count): void
    {
        file_put_contents($this->path, $data);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Password corpus integrity failed.');
        new LocalCompromisedPasswords($this->path, hash('sha256', $data), $count);
    }

    /**
     * Supplies representation failures without plaintext entries
     *
     * @return iterable<string, array{string, int}>
     */
    public static function malformed_corpora(): iterable
    {
        yield 'empty' => ['', 1];
        yield 'zero expected count' => [str_repeat('a', 64)."\n", 0];
        yield 'missing final newline' => [str_repeat('a', 64), 1];
        yield 'wrong count' => [str_repeat('a', 64)."\n", 2];
        yield 'uppercase hex' => [str_repeat('A', 64)."\n", 1];
        yield 'nonhex' => [str_repeat('z', 64)."\n", 1];
        yield 'truncated entry' => [str_repeat('a', 63)."\n", 1];
        yield 'CRLF' => [str_repeat('a', 64)."\r\n", 1];
        yield 'duplicate' => [str_repeat('a', 64)."\n".str_repeat('a', 64)."\n", 2];
        yield 'unsorted' => [str_repeat('b', 64)."\n".str_repeat('a', 64)."\n", 2];
        yield 'blank entry' => [str_repeat('a', 64)."\n\n", 2];
    }

    /**
     * Keeps fingerprints out of dumps and serialized diagnostics
     */
    public function test_diagnostics_hide_lookup_material(): void
    {
        $marker = hash('sha256', 'distinct-sensitive-marker42!');
        $data = $marker."\n";
        file_put_contents($this->path, $data);
        $checker = new LocalCompromisedPasswords($this->path, hash('sha256', $data), 1);
        ob_start();
        var_dump($checker);
        $dump = (string) ob_get_clean();
        foreach ([$marker, pack('H*', $marker)] as $representation) {
            self::assertStringNotContainsString($representation, $dump);
            self::assertStringNotContainsString($representation, print_r($checker, true));
            self::assertStringNotContainsString($representation, var_export($checker, true));
        }
        self::assertStringContainsString('verified local data', $dump);
        try {
            serialize($checker);
            self::fail('Expected serialization refusal.');
        } catch (RuntimeException $failure) {
            self::assertSame('Password corpus serialization is disabled.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }
}
