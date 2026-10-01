<?php

declare(strict_types=1);

namespace Tests\Support;

use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\SchemaValidator;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Tooling\OpenApi\Document;

/**
 * Class ApiContract
 *
 * Compares actual application responses with the repository-owned contract
 */
final class ApiContract
{
    /**
     * Checks a real bootstrap response against its operation and exact transport contract
     */
    public static function assertBootstrap(ResponseInterface $response, int $status): void
    {
        self::assertTransport($response, $status);
        self::builder()->getResponseValidator()->validate(
            new OperationAddress('/api/v1/auth/csrf', 'get'),
            $response
        );
    }

    /**
     * Checks the real public validation read against its documented response
     */
    public static function assertValidation(ResponseInterface $response, int $status): void
    {
        self::assertTransport($response, $status);
        self::builder()->getResponseValidator()->validate(
            new OperationAddress('/api/v1/validations/{form_name}', 'get'),
            $response
        );
    }

    /**
     * Checks an API routing failure without inventing a documented operation
     */
    public static function assertRoutingFailure(ResponseInterface $response, int $status, string $component): void
    {
        self::assertTransport($response, $status);
        /**
         * @var Response $definition
         */
        $definition = self::schema()->components->responses[$component];
        /**
         * @var Schema $schema
         */
        $schema = $definition->content['application/json']->schema;
        (new SchemaValidator())->validate(
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
            $schema
        );
    }

    /**
     * Checks mapper output at its own boundary rather than claiming an HTTP operation
     *
     * @param array<string, mixed> $body
     */
    public static function assertMappedBody(array $body, string $component): void
    {
        /**
         * @var Schema $schema
         */
        $schema = self::schema()->components->schemas[$component];
        (new SchemaValidator())->validate($body, $schema);
    }

    /**
     * Checks non-schema transport guarantees on real responses
     */
    private static function assertTransport(ResponseInterface $response, int $status): void
    {
        Assert::assertSame($status, $response->getStatusCode());
        Assert::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        Assert::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        Assert::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $response->getHeaderLine('X-Correlation-ID'));
        Assert::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        Assert::assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
    }

    /**
     * Reads and resolves only the local document
     */
    private static function builder(): ValidatorBuilder
    {
        static $json = null;
        $json ??= Document::generate();

        return (new ValidatorBuilder())->fromJson($json);
    }

    /**
     * Returns resolved reusable schemas
     */
    private static function schema(): OpenApi
    {
        return self::builder()->getResponseValidator()->getSchema();
    }
}
