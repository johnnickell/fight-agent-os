<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Validation\SchemaProjection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class SchemaProjectionTest
 *
 * Proves the runtime public-message allowlist does not publish private rule semantics
 */
final class SchemaProjectionTest extends TestCase
{
    /**
     * Maps selected rule semantics and peer names while leaving private fields unpublished
     */
    public function testProjectsOnlyQualifiedPublicRules(): void
    {
        $projection = SchemaProjection::project([self::registration()]);
        self::assertSame(['sample' => ['fields' => [
            'code'         => ['client_field' => 'code', 'rules' => [
                ['type' => 'Required', 'args' => [], 'message' => 'Enter code.', 'depends_on' => []],
                ['type' => 'Type', 'args' => ['string'], 'message' => 'Enter text.', 'depends_on' => []],
                ['type' => 'MinLength', 'args' => ['2'], 'message' => 'Use two characters.', 'depends_on' => []],
                ['type' => 'MaxLength', 'args' => ['8'], 'message' => 'Use at most eight.', 'depends_on' => []]
            ]],
            'confirm_code' => ['client_field' => 'confirmCode', 'rules' => [
                ['type' => 'Same', 'args' => ['code'], 'message' => 'Match code.', 'depends_on' => ['code']]
            ]]
        ]]], $projection);
    }

    /**
     * Refuses ambiguous public selections instead of exposing server-only rules or substituted messages
     *
     * @param list<string|int> $path
     */
    #[DataProvider('invalidSelections')]
    public function testRejectsUnsafeSelections(array $path, mixed $value, string $reason): void
    {
        $registration = self::registration();
        /** @var array<string|int, mixed> $target */
        $target = &$registration;
        foreach ($path as $key) {
            $target = &$target[$key];
        }
        $target = $value;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($reason);
        SchemaProjection::project([$registration]);
    }

    /**
     * Supplies invalid public selections independently of the projection algorithm
     *
     * @return iterable<string, array{list<string|int>, mixed, string}>
     */
    public static function invalidSelections(): iterable
    {
        yield 'invalid form name' => [['name'], '../private', 'Invalid or duplicate public form name.'];
        yield 'different form name' => [['name'], 'other', 'Public form name does not match its declaration.'];
        yield 'missing declaration' => [
            ['action'], MissingValidation::class, 'A public form needs one Validation declaration.'
        ];
        yield 'no fields' => [['fields'], [], 'Ambiguous or empty public field declaration.'];
        yield 'bad client identifier' => [
            ['fields', 'code', 'client_field'], '../code', 'Invalid or ambiguous public field mapping.'
        ];
        yield 'duplicate client name' => [
            ['fields', 'confirm_code', 'client_field'], 'code', 'Invalid or ambiguous public field mapping.'
        ];
        yield 'no rules' => [['fields', 'code', 'rules'], [], 'Invalid or ambiguous public field mapping.'];
        yield 'negative selection' => [['fields', 'code', 'rules', 0, 'index'], -1, 'Invalid public rule selection.'];
        yield 'absent selection' => [['fields', 'code', 'rules', 0, 'index'], 9, 'Invalid public rule selection.'];
        yield 'duplicate selection' => [['fields', 'code', 'rules', 1, 'index'], 0, 'Invalid public rule selection.'];
        yield 'substituted message' => [
            ['fields', 'code', 'rules', 0, 'message'], 'Replacement.', 'Public error text must match'
        ];
        yield 'unpublished peer' => [['fields'], [
            'confirm_code' => ['client_field' => 'confirmCode', 'rules' => [['index' => 0, 'message' => 'Match code.']]]
        ], 'Comparison requires a published peer field.'];
        foreach (
            [0 => ['Private type.', 'Only non-null string type'],
            1  => ['Private length.', 'Length must be a bounded decimal integer.'],
            2  => ['Private prefix.', 'Unsupported public rule semantics.']] as $index => [$message, $reason]
        ) {
            yield 'private rule '.$index => [['fields'], [
                'private_code' => [
                    'client_field' => 'privateCode', 'rules' => [['index' => $index, 'message' => $message]]
                ]
            ], $reason];
        }
    }

    /**
     * Refuses a second definition instead of replacing the approved public form
     */
    public function testDuplicateFormCannotOverrideTheAllowlist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or duplicate public form name.');
        SchemaProjection::project([self::registration(), self::registration()]);
    }

    /**
     * Selects the independently reviewed public subset
     *
     * @return array{name: string, action: class-string, fields: array<string, array{
     *     client_field: string, rules: list<array{index: int, message: string}>
     * }>}
     */
    private static function registration(): array
    {
        return ['name' => 'sample', 'action' => PublicMetadataInput::class, 'fields' => [
            'confirm_code' => [
                'client_field' => 'confirmCode', 'rules' => [['index' => 0, 'message' => 'Match code.']]
            ],
            'code'         => ['client_field' => 'code', 'rules' => [
                ['index' => 0, 'message' => 'Enter code.'],
                ['index' => 1, 'message' => 'Enter text.'],
                ['index' => 2, 'message' => 'Use two characters.'],
                ['index' => 3, 'message' => 'Use at most eight.']
            ]]
        ]];
    }
}
