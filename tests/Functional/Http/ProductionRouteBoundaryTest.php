<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ProductionRouteBoundaryTest extends TestCase
{
    public function test_an_unknown_production_route_is_a_jsend_not_found_error(): void
    {
        $app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 3));

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/not-a-production-route'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(
            ['status' => 'error', 'message' => 'Not found.'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function test_production_boot_exposes_no_proof_only_api_routes(): void
    {
        $app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 3));

        foreach (['/api/echo', '/api/fail'] as $path) {
            $response = $app->handle((new ServerRequestFactory())->createServerRequest('POST', $path));

            self::assertSame(404, $response->getStatusCode(), $path);
            self::assertSame(
                ['status' => 'error', 'message' => 'Not found.'],
                json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
                $path
            );
        }
    }
}
