<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Password;

use App\Domain\Security\Password\PasswordRejection;
use App\Domain\Security\Password\PasswordStructure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

/**
 * Proves Unicode code-point length and category acceptance boundaries
 */
final class PasswordStructureTest extends TestCase
{
    /**
     * Applies the documented rules to unmodified candidate bytes
     */
    #[DataProvider('candidates')]
    public function test_structure(#[SensitiveParameter] string $candidate, ?PasswordRejection $expected): void
    {
        self::assertSame($expected, (new PasswordStructure())->rejection($candidate));
    }

    /**
     * Supplies independent boundary and category examples
     *
     * @return iterable<string, array{string, ?PasswordRejection}>
     */
    public static function candidates(): iterable
    {
        yield 'seven code points' => ['Ab1!xyz', PasswordRejection::LENGTH];
        yield 'eight code points' => ['Ab1!xyzz', null];
        yield '128 code points' => ['Ab1!'.str_repeat('é', 124), null];
        yield '129 code points' => ['Ab1!'.str_repeat('é', 125), PasswordRejection::LENGTH];
        yield 'empty' => ['', PasswordRejection::LENGTH];
        yield 'invalid sequence' => ["Ab1!xyz\xc0\xaf", PasswordRejection::INVALID_UTF8];
        yield 'encoded surrogate' => ["Ab1!xyz\xed\xa0\x80", PasswordRejection::INVALID_UTF8];
        yield 'truncated sequence' => ["Ab1!xyz\xf0\x9f", PasswordRejection::INVALID_UTF8];
        yield 'no uppercase' => ['ab1!xyzz', PasswordRejection::UPPERCASE_REQUIRED];
        yield 'no lowercase' => ['AB1!XYZZ', PasswordRejection::LOWERCASE_REQUIRED];
        yield 'no number' => ['Abc!xyzz', PasswordRejection::NUMBER_REQUIRED];
        yield 'no punctuation or symbol' => ['Ab12xyzz', PasswordRejection::SYMBOL_REQUIRED];
        yield 'spaces are not symbols' => ['Ab12    ', PasswordRejection::SYMBOL_REQUIRED];
        yield 'unicode whitespace is not symbol' => ["Ab12\u{00a0}\u{2003}\t\n", PasswordRejection::SYMBOL_REQUIRED];
        yield 'spaces are allowed' => [' Ab1!xy ', null];
        yield 'newlines count as characters' => ["Ab1!xyz\n", null];
        yield 'embedded null is valid unicode' => ["Ab1!xyz\0", null];
        yield 'unicode letters and decimal number' => ['Ωé١!abcd', null];
        yield 'unicode other number and symbol' => ['Жé½€abcd', null];
        yield 'unicode letter number' => ['AbⅫ!xyzz', null];
        yield 'unicode punctuation' => ['Ab1。xyzz', null];
        yield 'emoji symbol' => ['Ab1😀xyzz', null];
        yield 'combining mark counts independently' => ["Ab1!xye\u{0301}", null];
        yield 'precomposed shorter equivalent' => ['Ab1!xyé', PasswordRejection::LENGTH];
        yield 'combining mark does not replace symbol' => ["Ab12xye\u{0301}", PasswordRejection::SYMBOL_REQUIRED];
        yield 'titlecase is not uppercase category' => ['ǅb1!xyzz', PasswordRejection::UPPERCASE_REQUIRED];
        yield 'long combining sequence' => ["Ab1!".str_repeat("e\u{0301}", 62), null];
    }
}
