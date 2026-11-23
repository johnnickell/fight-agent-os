<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Responder\Api\V1\Validations\ReadValidationResponder;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Protects the public validation response contract
 */
final class ReadValidationResponderTest extends TestCase
{
    /**
     * Returns the exact versioned schema without cache reuse or cookies
     */
    public function testPresentsOnlyTheApprovedSchema(): void
    {
        $schema = [
            'schema_version' => 1,
            'revision'       => str_repeat('a', 64),
            'form_name'      => 'sample',
            'fields'         => ['name' => ['client_field' => 'name', 'rules' => [[
                'type' => 'Required', 'args' => [], 'message' => 'Enter a name.', 'depends_on' => []
            ]]]]
        ];
        $responder = new ReadValidationResponder(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()));

        $response = $responder->respond($schema);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertFalse($response->hasHeader('Set-Cookie'));
        self::assertSame(
            ['status' => 'success', 'data' => $schema],
            json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)
        );
    }
}
