<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Auth;

use App\Adapter\Http\Api\V1\Auth\AuthOperation;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Application\Failure\PermissionDenied;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Class AuthEndpointGuard
 *
 * Applies the browser profile and endpoint shape to already source-validated JSON before admission
 */
final readonly class AuthEndpointGuard implements MiddlewareInterface
{
    /**
     * Constructs AuthEndpointGuard
     */
    public function __construct(
        private BrowserMutationGuard $browser,
        private AuthAdmission $admission,
        private AuthOperation $operation
    ) {
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
        if ($this->operation === AuthOperation::LOGIN) {
            if (
                $keys !== ['email', 'password', 'remember'] || !is_string($input['email'])
                || strlen($input['email']) > 254 || !is_string($input['password'])
                || !preg_match('//u', $input['password']) || mb_strlen($input['password'], 'UTF-8') < 1
                || mb_strlen($input['password'], 'UTF-8') > 128 || !is_bool($input['remember'])
            ) {
                throw new PermissionDenied();
            }
            try {
                if (EmailAddress::fromString($input['email'])->canonical() !== $input['email']) {
                    throw new PermissionDenied();
                }
            } catch (Throwable) {
                throw new PermissionDenied();
            }
        } elseif ($input !== []) {
            throw new PermissionDenied();
        }
        $network = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $this->admission->endpoint($this->operation, is_string($network) ? $network : '');

        return $handler->handle($request);
    }
}
