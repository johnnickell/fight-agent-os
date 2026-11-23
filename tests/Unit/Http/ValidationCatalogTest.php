<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Validations\ReadValidationAction;
use App\Adapter\Http\Responder\Api\V1\Validations\ReadValidationResponder;
use App\Adapter\Validation\Catalog;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Verifies public-form metadata selection and integrity without disk access
 */
final class ValidationCatalogTest extends TestCase
{
    /**
     * Publishes only the selected safe form with its exact revision and fields
     */
    public function testPublishedForm(): void
    {
        $forms = self::forms();
        $bytes = self::wire($forms);
        self::assertSame($bytes, Catalog::generation($forms));
        $action = $this->action(new Catalog(static fn (): string => $bytes, ['sample']));
        $response = $action->handle(
            new ServerRequestFactory()->createServerRequest('GET', '/api/v1/validations/sample'),
            new ResponseFactory()->createResponse(),
            ['form_name' => 'sample']
        );
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame([
            'status' => 'success',
            'data'   => [
                'schema_version' => 1,
                'revision'       => json_decode($bytes, true, flags: JSON_THROW_ON_ERROR)['revision'],
                'form_name'      => 'sample',
                'fields'         => $forms['sample']['fields']
            ]
        ], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * Distinguishes malformed names from well-formed unpublished names
     */
    public function testReadRefusals(): void
    {
        $catalog = new Catalog(static fn (): string => self::wire(self::forms()), ['sample']);
        self::assertNull($catalog->find('../private'));
        $action = $this->action($catalog);
        $request = new ServerRequestFactory()->createServerRequest('GET', '/api/v1/validations');
        $response = new ResponseFactory()->createResponse();
        try {
            $action->handle($request, $response, []);
            self::fail('Missing names are malformed.');
        } catch (HttpBadRequestException $exception) {
            self::assertSame(400, $exception->getCode());
        }
        $this->expectException(HttpNotFoundException::class);
        $action->handle($request, $response, ['form_name' => 'unlisted']);
    }

    /**
     * Rejects invalid generations rather than publishing partial data
     *
     * @param list<string|int> $path
     */
    #[DataProvider('invalidMetadata')]
    public function testInvalidMetadata(array $path, mixed $value, string $reason): void
    {
        $forms = self::forms();
        $target = &$forms;
        foreach ($path as $key) {
            $target = &$target[$key];
        }
        $target = $value;
        $this->assertUnavailable(self::wire($forms), $reason);
    }

    /**
     * Supplies malformed forms, fields, and rules with otherwise valid wire encodings
     *
     * @return iterable<string, array{list<string|int>, mixed, string}>
     */
    public static function invalidMetadata(): iterable
    {
        yield 'missing form' => [[], [], 'Incomplete public form catalog.'];
        yield 'empty form' => [['sample', 'fields'], (object) [], 'Invalid public form.'];
        yield 'extra form key' => [['sample', 'private'], 'secret', 'Invalid public form.'];
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
     * Rejects unavailable, unbounded, unsupported, noncanonical, and corrupted wire data
     *
     * @return iterable<string, array{string|false, ?string}>
     */
    public static function invalidWire(): iterable
    {
        yield 'no bytes' => [false, null];
        yield 'oversize' => [str_repeat(' ', 262145), null];
        yield 'missing fields' => ["{}\n", 'Unsupported public form catalog.'];
        yield 'alternate encoding' => [self::wire(self::forms())."\n", 'Noncanonical public form catalog.'];
        yield 'wrong checksum' => [
            self::wire(self::forms(), str_repeat('0', 64)), 'Public form catalog integrity failure.'
        ];
    }

    /**
     * Rejects invalid wire data with a stable external failure
     */
    #[DataProvider('invalidWire')]
    public function testInvalidWire(string|false $bytes, ?string $reason): void
    {
        $this->assertUnavailable($bytes, $reason);
    }

    /**
     * Asserts the sanitized failure and internal classification
     */
    private function assertUnavailable(string|false $bytes, ?string $reason): void
    {
        try {
            new Catalog(static fn (): string|false => $bytes, ['sample'])->find('sample');
            self::fail('Invalid artifact must not be served.');
        } catch (RuntimeException $exception) {
            self::assertSame('Public form catalog unavailable.', $exception->getMessage());
            self::assertSame($reason, $exception->getPrevious()?->getMessage());
        }
    }

    /**
     * Supplies a complete wire fixture covering every public rule family
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
     * Encodes test wire bytes without the product encoder
     *
     * @param array<string, mixed> $forms
     */
    private static function wire(array $forms, ?string $revision = null): string
    {
        $payload = ['schema_version' => 1, 'forms' => (object) $forms];
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        $revision ??= hash('sha256', json_encode($payload, $flags));

        return json_encode($payload + ['revision' => $revision], $flags)."\n";
    }

    /**
     * Constructs the action with in-memory transport inputs
     */
    private function action(Catalog $catalog): ReadValidationAction
    {
        return new ReadValidationAction($catalog, new ReadValidationResponder(new JSendResponseFactory(
            new ResponseFactory(),
            new StreamFactory()
        )));
    }
}
