<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Auth;

use App\Adapter\Http\Api\V1\Auth\AuthOperation;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Application\Failure\PermissionDenied;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ResetCompletionGuard
 *
 * Admits only checked reset fields under the shared browser and atomic peer budgets
 */
final readonly class ResetCompletionGuard implements MiddlewareInterface
{
    /**
     * Constructs ResetCompletionGuard
     */
    public function __construct(private BrowserMutationGuard $browser, private AuthAdmission $admission)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->browser->assertRequest($request);
        $input = $request->getAttribute(JsonBody::class);
        if (!is_array($input)) {
            throw new PermissionDenied();
        }
        $keys = array_keys($input);
        sort($keys);
        if ($keys !== ['credential', 'password', 'password_confirmation', 'user_id']) {
            throw new PermissionDenied();
        }
        // The link contract bounds opaque credential bytes, independently of Unicode field length.
        if (!is_string($input['credential']) || strlen($input['credential']) > 4096) {
            throw new PermissionDenied();
        }
        $network = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $this->admission->endpoint(AuthOperation::RESET_COMPLETION, is_string($network) ? $network : '');

        return $handler->handle($request);
    }
}
