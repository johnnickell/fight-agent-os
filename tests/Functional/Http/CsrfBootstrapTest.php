<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Class CsrfBootstrapTest
 *
 * Proves only the public route's application composition and dispatch boundary
 */
final class CsrfBootstrapTest extends TestCase
{
    /**
     * Reaches the real proof query through the versioned route
     */
    public function test_that_bootstrap_route_issues_proof(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('X-Correlation-ID', str_repeat('c', 32));

        $response = $app->handle($request);

        self::assertSame(
            'success',
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)['status']
        );
        self::assertSame(str_repeat('c', 32), $response->getHeaderLine('X-Correlation-ID'));
    }

    /**
     * Rejects a body through Slim routing without dispatching the query
     */
    public function test_that_invalid_body_never_dispatches_query(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $bus = new class implements QueryBus {
            public int $calls = 0;

            /**
             * @inheritDoc
             */
            public function fetch(Query $query): mixed
            {
                ++$this->calls;

                throw new \LogicException('Invalid input reached the query bus.');
            }

            /**
             * @inheritDoc
             */
            public function dispatch(QueryMessage $queryMessage): mixed
            {
                throw new \LogicException('Unexpected dispatch path.');
            }
        };
        $app->getContainer()?->set(QueryBus::class, static fn (): QueryBus => $bus);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withBody((new StreamFactory())->createStream('not-json'))
            ->withHeader('X-Correlation-ID', str_repeat('d', 32));

        $response = $app->handle($request);

        self::assertSame(0, $bus->calls);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(str_repeat('d', 32), $response->getHeaderLine('X-Correlation-ID'));
    }
}
