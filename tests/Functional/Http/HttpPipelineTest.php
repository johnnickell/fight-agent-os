<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class HttpPipelineTest extends TestCase
{
    public function test_the_native_slim_root_route_remains_available(): void
    {
        $app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 3));

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Fight Slim starter is ready.', (string) $response->getBody());
    }

    public function test_a_state_changing_json_request_is_parsed_before_the_route_handles_it(): void
    {
        $app = require sprintf('%s/tests/Fixture/Http/boot.php', dirname(__DIR__, 3));
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/echo')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream('{"message":"ready"}'));

        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['status' => 'success', 'data' => ['message' => 'ready']],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function test_invalid_json_is_returned_as_a_jsend_error_response(): void
    {
        $app = require sprintf('%s/tests/Fixture/Http/boot.php', dirname(__DIR__, 3));
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/echo')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream('{'));

        $response = $app->handle($request);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            ['status' => 'error', 'message' => 'Syntax error'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function test_downstream_failures_are_returned_as_jsend_error_responses(): void
    {
        $app = require sprintf('%s/tests/Fixture/Http/boot.php', dirname(__DIR__, 3));

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('POST', '/api/fail'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            ['status' => 'error', 'message' => 'The HTTP journey failed.'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
