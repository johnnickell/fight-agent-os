<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use App\Adapter\Validation\Catalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\ApiContract;

/**
 * Proves the real public read boundary and bounded failures without inventing a product form
 */
final class PublicValidationTest extends TestCase
{
    private string $directory;

    /**
     * Creates an isolated private runtime-catalog fixture
     */
    protected function setUp(): void
    {
        $this->directory = dirname(__DIR__, 3).'/.runs/test-validations/'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    /**
     * Removes owned fixture bytes after each request
     */
    protected function tearDown(): void
    {
        if (is_file($this->directory.'/catalog.json')) {
            unlink($this->directory.'/catalog.json');
        }
        rmdir($this->directory);
    }

    /**
     * Reads explicit safe metadata anonymously and returns no state or credentials
     */
    public function test_that_an_approved_complete_schema_is_public_and_matches_the_api_contract(): void
    {
        $this->installSafeCatalog();
        $response = $this->request('GET', '/api/v1/validations/sample_form');

        ApiContract::assertValidation($response, 200);
        $data = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR)['data'];
        self::assertSame(1, $data['schema_version']);
        self::assertSame('sample_form', $data['form_name']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $data['revision']);
        self::assertSame('confirmCode', $data['fields']['confirm_code']['client_field']);
        self::assertSame(['code'], $data['fields']['confirm_code']['rules'][0]['depends_on']);
        self::assertCount(2, $data['fields']['code']['rules']);
        self::assertFalse($response->hasHeader('Set-Cookie'));
        self::assertStringNotContainsString('private_rule', (string) $response->getBody());
    }

    /**
     * Rejects unapproved names and unsupported methods without reading arbitrary files
     */
    #[DataProvider('rejections')]
    public function test_that_public_input_rejections_are_safe(string $method, string $path, int $status): void
    {
        $this->installSafeCatalog();
        $response = $this->request($method, $path);
        if ($method === 'GET' && $status !== 405) {
            ApiContract::assertValidation($response, $status);
        } else {
            ApiContract::assertRoutingFailure($response, $status, 'MethodNotAllowed');
        }
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }

    /**
     * Supplies hostile and unknown path inputs to the actual route
     *
     * @return iterable<string, array{string, string, int}>
     */
    public static function rejections(): iterable
    {
        yield 'unknown' => ['GET', '/api/v1/validations/unlisted', 404];
        yield 'uppercase' => ['GET', '/api/v1/validations/BadName', 400];
        yield 'too long' => ['GET', '/api/v1/validations/'.str_repeat('a', 65), 400];
        yield 'dot traversal' => ['GET', '/api/v1/validations/..%2Fprivate', 404];
        yield 'encoded separator' => ['GET', '/api/v1/validations/sample%2Fform', 404];
        yield 'wrong method' => ['POST', '/api/v1/validations/sample_form', 405];
    }

    /**
     * Fails closed when the approved catalog is missing or damaged, never serving empty rules
     */
    public function test_that_unavailable_and_invalid_complete_catalogs_are_safe_failures(): void
    {
        $missing = $this->request('GET', '/api/v1/validations/sample_form');
        ApiContract::assertValidation($missing, 500);
        self::assertSame('Internal server error.', json_decode((string) $missing->getBody(), true)['message']);

        file_put_contents($this->directory.'/catalog.json', '{"schema_version":1,"forms":{}}');
        $invalid = $this->request('GET', '/api/v1/validations/sample_form');
        ApiContract::assertValidation($invalid, 500);
        self::assertSame('Internal server error.', json_decode((string) $invalid->getBody(), true)['message']);
        self::assertFalse($invalid->hasHeader('Set-Cookie'));
    }

    /**
     * Distinguishes a complete empty catalog from an unavailable one even for unapproved names
     */
    public function test_that_unknown_names_require_a_complete_catalog_with_no_registered_forms(): void
    {
        $name = '/api/v1/validations/sample_form';
        $missing = $this->request('GET', $name, '', [], []);
        ApiContract::assertValidation($missing, 500);
        self::assertSame('Internal server error.', json_decode((string) $missing->getBody(), true)['message']);

        file_put_contents($this->directory.'/catalog.json', '{"schema_version":1,"forms":{}}');
        $invalid = $this->request('GET', $name, '', [], []);
        ApiContract::assertValidation($invalid, 500);
        self::assertSame('Internal server error.', json_decode((string) $invalid->getBody(), true)['message']);
        self::assertFalse($invalid->hasHeader('Set-Cookie'));

        file_put_contents($this->directory.'/catalog.json', Catalog::generation([]));
        $complete = $this->request('GET', $name, '', [], []);
        ApiContract::assertValidation($complete, 404);
    }

    /**
     * Requires complete output for unknown names even when another form is approved
     */
    public function test_that_an_unknown_name_does_not_mask_a_missing_catalog(): void
    {
        $response = $this->request('GET', '/api/v1/validations/unlisted');
        ApiContract::assertValidation($response, 500);
    }

    /**
     * Never serves a structurally valid catalog whose published bytes no longer match its revision
     */
    public function test_that_a_changed_public_message_without_a_matching_revision_is_unavailable(): void
    {
        $this->installSafeCatalog();
        $file = $this->directory.'/catalog.json';
        $bytes = file_get_contents($file);
        self::assertIsString($bytes);
        file_put_contents($file, str_replace('Code is required.', 'Private detail.', $bytes));

        $response = $this->request('GET', '/api/v1/validations/sample_form');
        ApiContract::assertValidation($response, 500);
        self::assertStringNotContainsString('Private detail.', (string) $response->getBody());
    }

    /**
     * Rejects extra request input without changing the public read exemption
     */
    public function test_that_request_body_and_query_are_not_accepted(): void
    {
        $this->installSafeCatalog();
        $body = $this->request('GET', '/api/v1/validations/sample_form', 'secret', ['Content-Type' => 'text/plain']);
        ApiContract::assertValidation($body, 400);
        self::assertSame('fail', json_decode((string) $body->getBody(), true)['status']);
        $query = $this->request('GET', '/api/v1/validations/sample_form?secret=x');
        ApiContract::assertValidation($query, 400);
        self::assertSame('fail', json_decode((string) $query->getBody(), true)['status']);
    }

    /**
     * Exercises legitimate catalog bytes supplied through the runtime reader seam
     */
    private function installSafeCatalog(): void
    {
        $required = [
            'type'       => 'Required',
            'args'       => [],
            'message'    => 'Code is required.',
            'depends_on' => []
        ];
        $minimum = [
            'type'       => 'MinLength',
            'args'       => ['2'],
            'message'    => 'Use at least two characters.',
            'depends_on' => []
        ];
        $comparison = [
            'type'       => 'Same',
            'args'       => ['code'],
            'message'    => 'Codes must match.',
            'depends_on' => ['code']
        ];
        $fields = [
            'code'         => ['client_field' => 'code', 'rules' => [$required, $minimum]],
            'confirm_code' => ['client_field' => 'confirmCode', 'rules' => [$comparison]]
        ];
        $forms = ['sample_form' => ['fields' => $fields]];
        file_put_contents($this->directory.'/catalog.json', Catalog::generation($forms));
    }

    /**
     * Sends a real request with a test-owned runtime catalog, not a fabricated Action
     *
     * @param string                $method
     * @param string                $path
     * @param string                $body
     * @param array<string, string> $headers
     * @param array<int, string>    $allowedNames
     */
    private function request(
        string $method,
        string $path,
        string $body = '',
        array $headers = [],
        array $allowedNames = ['sample_form']
    ): \Psr\Http\Message\ResponseInterface {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $file = $this->directory.'/catalog.json';
        $app->getContainer()?->set(Catalog::class, static fn (): Catalog => new Catalog($file, $allowedNames));
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://agent-os.test'.$path)
            ->withBody((new StreamFactory())->createStream($body));
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $app->handle($request);
    }
}
