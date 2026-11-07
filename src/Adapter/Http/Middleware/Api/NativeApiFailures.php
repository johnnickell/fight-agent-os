<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api;

use App\Adapter\Http\Api\Failure\RegistrationInputFailure;
use App\Domain\Workspace\RegistrationConflict;
use App\Domain\Workspace\RegistrationInputRejected;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\InvitationAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\User\Exception\LoginRejectedException;
use Fight\Common\Adapter\Http\Error\SafeHttpFailure;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\InvalidInput;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\EventSourcing\Exception\OptimisticConcurrencyException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpGoneException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpTooManyRequestsException;
use Slim\Exception\HttpUnauthorizedException;
use Throwable;

/**
 * Class NativeApiFailures
 *
 * Translates explicit native protocol and consumed package refusals before Common handles them
 */
final readonly class NativeApiFailures implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path !== '/api' && !str_starts_with($path, '/api/')) {
            return $handler->handle($request);
        }

        try {
            return $handler->handle($request);
        } catch (Throwable $failure) {
            // No generic HttpException code/header mapping, diagnostic matching or reporting here.
            throw match (true) {
                $failure instanceof HttpMethodNotAllowedException => SafeHttpFailure::methodNotAllowed(
                    implode(', ', $failure->getAllowedMethods())
                ),
                $failure instanceof HttpNotFoundException => SafeHttpFailure::fromNative(404),
                $failure instanceof HttpBadRequestException => SafeHttpFailure::badRequest(),
                $failure instanceof HttpUnauthorizedException => SafeHttpFailure::authenticationRequired('Bearer'),
                $failure instanceof HttpForbiddenException => SafeHttpFailure::fromNative(403),
                $failure instanceof HttpGoneException => SafeHttpFailure::fromNative(410),
                $failure instanceof HttpTooManyRequestsException => SafeHttpFailure::tooManyRequests(),
                $failure instanceof LoginRejectedException => new AuthenticationRequired(previous: $failure),
                $failure instanceof InvitationAdministrationAuthorizationException =>
                    new PermissionDenied(previous: $failure),
                $failure instanceof RegistrationInputRejected => new RegistrationInputFailure(previous: $failure),
                $failure instanceof RegistrationConflict,
                $failure instanceof OptimisticConcurrencyException => new StateConflict(previous: $failure),
                $failure instanceof ValidationException => new InvalidInput(previous: $failure),
                default => $failure,
            };
        }
    }
}
