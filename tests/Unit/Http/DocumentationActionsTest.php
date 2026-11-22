<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Documentation\ReadPrivateOpenApiAction;
use App\Adapter\Http\Action\Documentation\ReadSwaggerBootstrapAction;
use App\Adapter\Http\Documentation\PrivateOpenApi;
use App\Adapter\Http\Documentation\PrivateOpenApiResponder;
use App\Adapter\Http\Documentation\SwaggerBootstrapResponder;
use App\Adapter\Http\Web\PublicRuntimeConfiguration;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use PHPUnit\Framework\TestCase;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Exercises the private documentation presentation and deployment eligibility boundary
 */
final class DocumentationActionsTest extends TestCase
{
    /**
     * Keeps viewer initialization unavailable in an ineligible environment
     */
    public function test_that_disabled_bootstrap_is_not_found(): void
    {
        $action = new ReadSwaggerBootstrapAction(
            new PublicRuntimeConfiguration(),
            new SwaggerBootstrapResponder($this->responses())
        );
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/documentation/bootstrap');
        $this->expectException(HttpNotFoundException::class);

        $action->handle($request, (new ResponseFactory())->createResponse());
    }

    /**
     * Returns exactly the viewer initialization contract with no reusable private response
     */
    public function test_that_enabled_bootstrap_returns_only_viewer_protocol(): void
    {
        $action = new ReadSwaggerBootstrapAction(
            new PublicRuntimeConfiguration(swaggerAvailable: true),
            new SwaggerBootstrapResponder($this->responses())
        );
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/documentation/bootstrap');

        $response = $action->handle($request, (new ResponseFactory())->createResponse());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame([
            'status' => 'success',
            'data'   => ['viewer_version' => 1, 'document_path' => '/api/v1/documentation/openapi']
        ], json_decode((string) $response->getBody(), true, 8, JSON_THROW_ON_ERROR));
    }

    /**
     * Does not attempt to present private documentation in an ineligible environment
     */
    public function test_that_disabled_document_is_not_found(): void
    {
        $action = new ReadPrivateOpenApiAction(
            new PublicRuntimeConfiguration(),
            new PrivateOpenApi(''),
            new PrivateOpenApiResponder($this->responses())
        );
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/documentation/openapi');
        $this->expectException(HttpNotFoundException::class);

        $action->handle($request, (new ResponseFactory())->createResponse());
    }

    /**
     * Preserves the authorized document bytes while prohibiting cache reuse and MIME sniffing
     */
    public function test_that_document_presentation_preserves_bytes_and_private_headers(): void
    {
        $document = '{"openapi":"3.0.3","paths":{}}';
        $response = (new PrivateOpenApiResponder($this->responses()))->respond(
            (new ResponseFactory())->createResponse(),
            $document
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($document, (string) $response->getBody());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
    }

    /**
     * Reports deployment failure without parser or filesystem diagnostics
     */
    public function test_that_unavailable_document_is_reported_safely(): void
    {
        $response = (new PrivateOpenApiResponder($this->responses()))->unavailable();

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame(['status' => 'error', 'message' => 'Service unavailable.'], json_decode(
            (string) $response->getBody(),
            true,
            8,
            JSON_THROW_ON_ERROR
        ));
    }

    /**
     * Uses the production envelope factory without external dependencies
     */
    private function responses(): JSendResponseFactory
    {
        return new JSendResponseFactory(new ResponseFactory(), new StreamFactory());
    }
}
