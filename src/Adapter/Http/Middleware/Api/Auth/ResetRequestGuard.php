<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Auth;

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
 * Class ResetRequestGuard
 *
 * Requires the existing browser profile and one canonical email before atomic admission
 */
final readonly class ResetRequestGuard implements MiddlewareInterface
{
    /**
     * Constructs ResetRequestGuard
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
        if (
            !is_array($input) || array_keys($input) !== ['email'] || !is_string($input['email'])
            || strlen($input['email']) > 254
        ) {
            throw new PermissionDenied();
        }
        try {
            $email = EmailAddress::fromString($input['email']);
            if ($email->canonical() !== $input['email']) {
                throw new PermissionDenied();
            }
        } catch (Throwable) {
            throw new PermissionDenied();
        }
        $network = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $this->admission->passwordReset($email, is_string($network) ? $network : '');

        return $handler->handle($request);
    }
}
