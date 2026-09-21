<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class BootstrapTest extends TestCase
{
    public function test_that_root_route_resolves_the_fight_common_container_service(): void
    {
        $app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__));
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Fight Slim starter is ready.', (string) $response->getBody());
    }
}
