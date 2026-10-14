<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use App\Adapter\Validation\Catalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Proves the runtime reader rejects unsafe public metadata, not that the export tool works
 */
final class ValidationCatalogTest extends TestCase
{
    private string $directory;
    private string $file;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->directory = dirname(__DIR__, 3).'/.runs/test-catalog/'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->file = $this->directory.'/catalog.json';
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    /**
     * Returns exact qualified scalar and comparison semantics from private runtime bytes
     */
    public function testReadsCompleteMetadataWithoutReflection(): void
    {
        $forms = self::forms();
        $this->install($forms);
        $catalog = new Catalog($this->file, ['sample']);
        $result = $catalog->find('sample');
        self::assertNotNull($result);
        self::assertSame($forms['sample']['fields'], $result['fields']);
        self::assertSame(1, $result['schema_version']);
        self::assertSame('sample', $result['form_name']);
        self::assertNull($catalog->find('../private'));
        self::assertNull($catalog->find('unlisted'));
    }

    /**
     * Fails the entire generation when one form violates the public semantics
     *
     * @param list<string|int> $path
     */
    #[DataProvider('invalidMetadata')]
    public function testRejectsUnsafeMetadata(array $path, mixed $value, string $reason): void
    {
        $forms = self::forms();
        $target = &$forms;
        foreach ($path as $key) {
            $target = &$target[$key];
        }
        $target = $value;
        $this->install($forms);
        try {
            (new Catalog($this->file, ['sample']))->find('sample');
            self::fail('Unsafe metadata must not be served.');
        } catch (RuntimeException $exception) {
            self::assertSame('Public form catalog unavailable.', $exception->getMessage());
            self::assertSame($reason, $exception->getPrevious()?->getMessage());
        }
    }

    /**
     * Supplies independently chosen invalid public semantics
     *
     * @return iterable<string, array{list<string|int>, mixed, string}>
     */
    public static function invalidMetadata(): iterable
    {
        yield 'missing form' => [[], [], 'Incomplete public form catalog.'];
        yield 'empty form' => [['sample', 'fields'], (object) [], 'Invalid public form.'];
        yield 'unbounded form' => [['sample', 'private'], 'secret', 'Invalid public form.'];
        yield 'bad client name' => [['sample', 'fields', 'code', 'client_field'], '../code', 'Invalid public field.'];
        yield 'duplicate client name' => [
            ['sample', 'fields', 'confirm_code', 'client_field'], 'code', 'Invalid public field.'
        ];
        yield 'no rules' => [['sample', 'fields', 'code', 'rules'], [], 'Invalid public field.'];
        yield 'unsafe text' => [
            ['sample', 'fields', 'code', 'rules', 0, 'message'], "Private\ntext", 'Invalid public rule.'
        ];
        yield 'extra rule data' => [['sample', 'fields', 'code', 'rules', 0, 'secret'], 'x', 'Invalid public rule.'];
        yield 'unsupported rule' => [
            ['sample', 'fields', 'code', 'rules', 0, 'type'], 'Email', 'Unsupported public rule.'
        ];
        yield 'required args' => [['sample', 'fields', 'code', 'rules', 0, 'args'], ['x'], 'Unsupported public rule.'];
        yield 'non-string type' => [['sample', 'fields', 'code', 'rules', 1, 'args'], ['int'], 'Invalid public type.'];
        yield 'negative length' => [['sample', 'fields', 'code', 'rules', 2, 'args'], ['-1'], 'Invalid public length.'];
        yield 'integer length' => [['sample', 'fields', 'code', 'rules', 2, 'args'], [2], 'Invalid public length.'];
        yield 'large length' => [['sample', 'fields', 'code', 'rules', 3, 'args'], ['10000'], 'Invalid public length.'];
        yield 'missing peer' => [
            ['sample', 'fields', 'confirm_code', 'rules', 0, 'args'], ['absent'], 'Invalid public comparison.'
        ];
        yield 'wrong dependency' => [
            ['sample', 'fields', 'confirm_code', 'rules', 0, 'depends_on'], ['other'], 'Invalid public comparison.'
        ];
    }

    /**
     * Refuses alternate encodings and symlinked artifacts even when their semantic data is valid
     */
    public function testRejectsNoncanonicalAndLinkedCatalogs(): void
    {
        $this->install(self::forms());
        $bytes = file_get_contents($this->file);
        self::assertIsString($bytes);
        file_put_contents($this->file, $bytes."\n");
        try {
            (new Catalog($this->file, ['sample']))->find('sample');
            self::fail('Noncanonical bytes must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Noncanonical public form catalog.', $exception->getPrevious()?->getMessage());
        }
        rename($this->file, $this->directory.'/other.json');
        symlink($this->directory.'/other.json', $this->file);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Public form catalog unavailable.');
        (new Catalog($this->file, ['sample']))->find('sample');
    }

    /**
     * Supplies a reviewed complete form with every publicly supported rule family
     *
     * @return array<string, mixed>
     */
    private static function forms(): array
    {
        return ['sample' => ['fields' => [
            'code'         => ['client_field' => 'code', 'rules' => [
                ['type' => 'Required', 'args' => [], 'message' => 'Enter code.', 'depends_on' => []],
                ['type' => 'Type', 'args' => ['string'], 'message' => 'Enter text.', 'depends_on' => []],
                ['type' => 'MinLength', 'args' => ['2'], 'message' => 'Use two characters.', 'depends_on' => []],
                ['type' => 'MaxLength', 'args' => ['8'], 'message' => 'Use at most eight.', 'depends_on' => []]
            ]],
            'confirm_code' => ['client_field' => 'confirmCode', 'rules' => [
                ['type' => 'Same', 'args' => ['code'], 'message' => 'Match code.', 'depends_on' => ['code']]
            ]]
        ]]];
    }

    /**
     * Writes the documented wire shape with an independently computed revision
     *
     * @param array<string, mixed> $forms
     */
    private function install(array $forms): void
    {
        $payload = ['schema_version' => 1, 'forms' => (object) $forms];
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        $revision = hash('sha256', json_encode($payload, $flags));
        file_put_contents($this->file, json_encode($payload + ['revision' => $revision], $flags)."\n");
    }
}
