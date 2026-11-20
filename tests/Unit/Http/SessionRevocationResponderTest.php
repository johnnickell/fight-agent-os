<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Responder\Api\V1\Sessions\SessionRevocationResponder;
use Exception;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\InvalidInput;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Error\Exception\RuleViolation;
use Fight\Common\Application\Error\Exception\StateConflict;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Throwable;

/**
 * Protects the explicit non-enumerating public failure mapping including optimistic conflicts
 */
final class SessionRevocationResponderTest extends TestCase
{
    /**
     * Maps only known outcomes without copying private exception details
     *
     * @param Throwable $failure
     */
    #[DataProvider('failures')]
    public function testKnownFailure(Throwable $failure): void
    {
        $next = $this->createStub(\Psr\Http\Server\RequestHandlerInterface::class);
        $next->method('handle')->willReturnCallback(fn() => $this->responder()->respond(
            static function () use ($failure): void {
                throw $failure;
            }
        ));
        $response = \Tests\Support\ApiErrors::boundary(new \Monolog\Logger('test'))->process(
            (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest(
                'DELETE',
                '/api/v1/me/sessions/target'
            ),
            $next
        );
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $expected = ['status' => 'fail', 'data' => ['message' => 'State conflict.']];
        if ($failure instanceof RuleViolation) {
            $expected = ['status' => 'fail', 'data' => [
                'message' => 'Use Logout for the current session.', 'intent' => 'use_logout'
            ]];
        }
        self::assertSame($expected, json_decode((string) $response->getBody(), true));
    }

    /**
     * Creates the actual public response mapper
     */
    private function responder(): SessionRevocationResponder
    {
        return new SessionRevocationResponder(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()));
    }

    /**
     * Supplies every allowlisted package failure
     *
     * @return iterable<array{Throwable}>
     */
    public static function failures(): iterable
    {
        yield [new RuleViolation(
            'The current refresh session cannot be revoked through self-service session management.'
        )];
        yield [new StateConflict('The refresh session changed concurrently.')];
        yield [new ResourceNotFound('The refresh session is not authoritative.')];
        yield [new PermissionDenied('Session administration rejected.')];
        yield [new InvalidInput('Administrative session revocation requires a reason.')];
    }

    /**
     * Rejects unknown failures for the sanitized system-error boundary rather than asserting a safe conflict
     */
    #[DataProvider('unrelatedFailures')]
    public function testUnrelatedFailure(Exception $failure): void
    {
        $this->expectExceptionObject($failure);
        $this->responder()->respond(
            static function () use ($failure): void {
                throw $failure;
            }
        );
    }

    /**
     * Supplies failures that must retain their original classification
     *
     * @return iterable<array{Exception}>
     */
    public static function unrelatedFailures(): iterable
    {
        yield [new LogicException('private-storage-marker')];
        yield [new PermissionDenied()];
        yield [new InvalidInput('Unrelated invalid input.')];
        yield [new ResourceNotFound('Unrelated resource.')];
        yield [new RuleViolation('Unrelated rule.')];
    }
}
