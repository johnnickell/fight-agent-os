<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Middleware\ApiFailureMapper;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Failure\PermissionDenied;
use App\Application\Failure\ResourceNotFound;
use App\Application\Failure\StateConflict;
use Fight\Common\Application\Validation\Exception\ValidationException as TransportValidationException;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Exception\ValidationException as DomainValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Throwable;

/**
 * Proves allowlisted classification without granting generic exceptions public status
 */
final class ApiFailureMapperTest extends TestCase
{
    /**
     * Enumerates the supported status and JSend shapes
     *
     * @return iterable<string, array{Throwable, int, array<string, mixed>}>
     */
    public static function known_failures(): iterable
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/secret');
        yield 'transport validation' => [
            TransportValidationException::fromErrors(['password' => ['secret']]),
            400,
            ['status' => 'fail', 'data' => ['fields' => ['body' => ['Invalid input.']]]]
        ];
        yield 'domain validation' => [
            new DomainValidationException(['private_id' => ['secret']]),
            422,
            ['status' => 'fail', 'data' => ['fields' => ['input' => ['Invalid value.']]]]
        ];
        yield 'authentication' => [new AuthenticationRequired('secret'), 401, self::error('Unauthorized.')];
        yield 'authorization' => [new PermissionDenied('secret'), 403, self::error('Forbidden.')];
        yield 'stale conflict' => [new StateConflict('secret'), 409, self::error('Conflict.')];
        yield 'resource not found' => [new ResourceNotFound('secret'), 404, self::error('Not found.')];
        yield 'framework bad request' => [
            new HttpBadRequestException($request, 'secret'), 400, self::error('Bad request.')
        ];
        yield 'framework auth' => [
            new HttpUnauthorizedException($request, 'secret'), 401, self::error('Unauthorized.')
        ];
        yield 'framework forbidden' => [
            new HttpForbiddenException($request, 'secret'), 403, self::error('Forbidden.')
        ];
        yield 'framework route' => [
            new HttpNotFoundException($request, 'secret'), 404, self::error('Not found.')
        ];
        yield 'framework method' => [
            new HttpMethodNotAllowedException($request, 'secret'), 405, self::error('Method not allowed.')
        ];
    }

    /**
     * Ignores exception messages and returns only safe allowlisted presentations
     *
     * @param Throwable             $failure
     * @param integer               $status
     * @param array<string, mixed>  $body
     */
    #[DataProvider('known_failures')]
    public function test_that_known_failures_are_sanitized(Throwable $failure, int $status, array $body): void
    {
        $classification = (new ApiFailureMapper())->classify($failure);

        self::assertSame([$status, $body], [$classification?->status, $classification?->envelope->toArray()]);
    }

    /**
     * Does not turn a generic lookup or arbitrary failure into a resource miss
     */
    public function test_that_generic_lookup_is_not_not_found(): void
    {
        self::assertNull((new ApiFailureMapper())->classify(new LookupException('secret/path')));
    }

    /**
     * Does not infer status from exception codes or messages
     */
    public function test_that_unknown_exception_is_not_public(): void
    {
        self::assertNull((new ApiFailureMapper())->classify(new RuntimeException('Not found.', 404)));
    }

    /**
     * Constructs a safe error representation
     *
     * @return array{status: string, message: string}
     */
    private static function error(string $message): array
    {
        return ['status' => 'error', 'message' => $message];
    }
}
