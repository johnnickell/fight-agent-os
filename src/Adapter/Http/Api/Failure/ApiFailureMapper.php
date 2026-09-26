<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Failure;

use App\Adapter\Http\Api\Validation\InputFailures;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Failure\PermissionDenied;
use App\Application\Failure\ResourceNotFound;
use App\Application\Failure\StateConflict;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Application\Validation\Exception\ValidationException as TransportValidationException;
use Fight\Common\Domain\EventSourcing\Exception\OptimisticConcurrencyException;
use Fight\Common\Domain\Exception\ValidationException as DomainValidationException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpGoneException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpTooManyRequestsException;
use Slim\Exception\HttpUnauthorizedException;
use Throwable;

/**
 * Class ApiFailureMapper
 *
 * Classifies only declared application and framework failures
 * Do not infer a public status from arbitrary exception codes, messages or generic lookups
 */
final readonly class ApiFailureMapper
{
    /**
     * Returns a safe public failure or null for an unknown throwable
     */
    public function classify(Throwable $exception): ?MappedApiFailure
    {
        if ($exception instanceof TransportValidationException) {
            return new MappedApiFailure(400, JSendEnvelope::fail(new InputFailures([
                'body' => ['Invalid input.']
            ])));
        }

        if ($exception instanceof DomainValidationException) {
            return new MappedApiFailure(422, JSendEnvelope::fail(new InputFailures([
                'input' => ['Invalid value.']
            ])));
        }

        $known = match (true) {
            $exception instanceof AuthenticationRequired,
            $exception instanceof HttpUnauthorizedException => [401, 'Unauthorized.'],
            $exception instanceof PermissionDenied,
            $exception instanceof HttpForbiddenException => [403, 'Forbidden.'],
            $exception instanceof StateConflict,
            $exception instanceof OptimisticConcurrencyException => [409, 'Conflict.'],
            $exception instanceof ResourceNotFound,
            $exception instanceof HttpNotFoundException => [404, 'Not found.'],
            $exception instanceof HttpBadRequestException => [400, 'Bad request.'],
            $exception instanceof HttpMethodNotAllowedException => [405, 'Method not allowed.'],
            $exception instanceof HttpGoneException => [410, 'Gone.'],
            $exception instanceof HttpTooManyRequestsException => [429, 'Too many requests.'],
            default => null
        };

        return $known === null ? null : new MappedApiFailure($known[0], JSendEnvelope::error($known[1]));
    }
}
