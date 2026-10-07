<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Validation\Catalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class PublicMetadataSafetyTest
 *
 * Proves identifier and message bounds used by the runtime publication boundary
 */
final class PublicMetadataSafetyTest extends TestCase
{
    /**
     * Separates canonical wire identifiers from bounded camelCase client fields
     */
    #[DataProvider('identifiers')]
    public function testIdentifierBoundaries(string $name, bool $wire, bool $client): void
    {
        self::assertSame($wire, Catalog::identifier($name));
        self::assertSame($client, Catalog::clientIdentifier($name));
    }

    /**
     * Supplies canonical and hostile names independently of the implementation pattern
     *
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function identifiers(): iterable
    {
        yield 'minimal' => ['a', true, true];
        yield 'digits after first' => ['a1', true, true];
        yield 'wire spelling' => ['confirm_code', true, false];
        yield 'client spelling' => ['confirmCode', false, true];
        yield 'maximum' => [str_repeat('a', 64), true, true];
        yield 'overlong' => [str_repeat('a', 65), false, false];
        yield 'empty' => ['', false, false];
        yield 'leading uppercase' => ['Code', false, false];
        yield 'leading digit' => ['1code', false, false];
        yield 'leading underscore' => ['_code', false, false];
        yield 'trailing newline' => ["code\n", false, false];
        yield 'NUL suffix' => ["code\0", false, false];
        yield 'traversal' => ['../code', false, false];
        yield 'hyphen' => ['confirm-code', false, false];
        yield 'Unicode confusable' => ['cоde', false, false];
        yield 'space' => ['code name', false, false];
    }

    /**
     * Allows only bounded UTF-8 single-line public messages
     */
    #[DataProvider('messages')]
    public function testMessageBoundaries(string $message, bool $expected): void
    {
        self::assertSame($expected, Catalog::message($message));
    }

    /**
     * Supplies byte and character boundaries relevant to safe metadata responses
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function messages(): iterable
    {
        yield 'normal' => ['Enter a code.', true];
        yield 'UTF-8' => ['Entrez un prénom.', true];
        yield 'maximum bytes' => [str_repeat('x', 200), true];
        yield 'too many bytes' => [str_repeat('x', 201), false];
        yield 'multibyte maximum' => [str_repeat('é', 100), true];
        yield 'multibyte overflow' => [str_repeat('é', 101), false];
        yield 'empty' => ['', false];
        yield 'NUL' => ["a\0b", false];
        yield 'newline' => ["a\nb", false];
        yield 'carriage return' => ["a\rb", false];
        yield 'tab' => ["a\tb", false];
        yield 'delete' => ["a\x7fb", false];
        yield 'invalid UTF-8' => ["a\xff", false];
    }
}
