<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Slim\Exception\HttpException;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Class ApplicationSmokeTest
 */
final class ApplicationSmokeTest extends TestCase
{
    /**
     * Verifies the agent os root is available
     */
    public function testTheAgentOsRootIsAvailable(): void
    {
        $app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 3));

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('Fight Agent OS is ready.', (string) $response->getBody());
    }

    /**
     * Verifies an unknown route returns a safe not found response
     */
    public function testAnUnknownRouteReturnsASafeNotFoundResponse(): void
    {
        $app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 3));

        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/not-an-agent-os-route')
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['status' => 'error', 'message' => 'Not found.'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Verifies a generic http exception is sanitized and correlated with its log
     */
    public function testAGenericHttpExceptionIsSanitizedAndCorrelatedWithItsLog(): void
    {
        $testApp = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 3));
        $logger = $testApp->getContainer()?->get(LoggerInterface::class);
        self::assertInstanceOf(Logger::class, $logger);
        $testHandler = new TestHandler(Level::Error);
        $logger->pushHandler($testHandler);

        $failurePath = '/_test/http-exception';
        $sensitiveMessage = 'credential=do-not-return path=/srv/private/provider.php';
        $testApp->get($failurePath, function (ServerRequestInterface $request) use ($sensitiveMessage): never {
            throw new HttpException($request, $sensitiveMessage, 500);
        });

        $response = $testApp->handle((new ServerRequestFactory())->createServerRequest('GET', $failurePath));
        $body = (string) $response->getBody();
        $correlationId = $response->getHeaderLine('X-Correlation-ID');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $correlationId);
        self::assertSame(
            ['status' => 'error', 'message' => 'Internal server error.'],
            json_decode($body, true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertStringNotContainsString($sensitiveMessage, $body);
        self::assertStringNotContainsString(HttpException::class, $body);
        self::assertStringNotContainsString(__FILE__, $body);

        $records = $testHandler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('Unhandled HTTP request failure.', $records[0]->message);
        self::assertSame($correlationId, $records[0]->context['correlation_id']);
        self::assertSame('GET', $records[0]->context['request_method']);
        self::assertSame($failurePath, $records[0]->context['request_path']);
        self::assertSame($sensitiveMessage, $records[0]->context['exception']->getMessage());
    }

    /**
     * Verifies an unexpected failure is sanitized and correlated with its log
     */
    public function testAnUnexpectedFailureIsSanitizedAndCorrelatedWithItsLog(): void
    {
        $productionApp = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 3));
        $requestFactory = new ServerRequestFactory();
        $failurePath = '/_test/unexpected-error';

        $productionResponse = $productionApp->handle($requestFactory->createServerRequest('GET', $failurePath));
        self::assertSame(404, $productionResponse->getStatusCode());

        $testApp = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 3));
        $logger = $testApp->getContainer()?->get(LoggerInterface::class);
        self::assertInstanceOf(Logger::class, $logger);
        $testHandler = new TestHandler(Level::Error);
        $logger->pushHandler($testHandler);

        $sensitiveMessage = 'credential=do-not-return path=/srv/private/provider.php';
        $testApp->get($failurePath, function () use ($sensitiveMessage): never {
            throw new RuntimeException($sensitiveMessage);
        });

        $response = $testApp->handle($requestFactory->createServerRequest('GET', $failurePath));
        $body = (string) $response->getBody();
        $correlationId = $response->getHeaderLine('X-Correlation-ID');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $correlationId);
        self::assertSame(
            ['status' => 'error', 'message' => 'Internal server error.'],
            json_decode($body, true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertStringNotContainsString($sensitiveMessage, $body);
        self::assertStringNotContainsString('RuntimeException', $body);
        self::assertStringNotContainsString(__FILE__, $body);

        $records = $testHandler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('Unhandled HTTP request failure.', $records[0]->message);
        self::assertSame($correlationId, $records[0]->context['correlation_id']);
        self::assertSame('GET', $records[0]->context['request_method']);
        self::assertSame($failurePath, $records[0]->context['request_path']);
        self::assertSame($sensitiveMessage, $records[0]->context['exception']->getMessage());
    }
}
