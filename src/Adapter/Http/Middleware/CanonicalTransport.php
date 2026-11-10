<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware;

use App\Adapter\Security\HmacCsrfProofs;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Class CanonicalTransport
 *
 * Requires direct canonical HTTPS authority before production request dispatch
 */
final readonly class CanonicalTransport implements MiddlewareInterface
{
    /**
     * Constructs CanonicalTransport
     */
    public function __construct(private HmacCsrfProofs $proofs, private bool $production)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->production) {
            return $handler->handle($request);
        }
        $uri = $request->getUri();
        $origin = $uri->getScheme().'://'.$uri->getAuthority();
        $rawHost = $request->getServerParams()['HTTP_HOST'] ?? $request->getHeaderLine('Host');
        $forwarded = false;
        foreach (array_keys($request->getHeaders()) as $name) {
            if (preg_match('/\A(?:forwarded|x-forwarded-.*|x-original-.*)\z/iD', $name)) {
                $forwarded = true;
            }
        }
        if (
            !$forwarded && $this->proofs->acceptsOrigin($origin)
            && is_string($rawHost) && $this->proofs->acceptsOrigin('https://'.$rawHost)
            && count($request->getHeader('Host')) === 1 && $uri->getUserInfo() === ''
            && $uri->getFragment() === ''
        ) {
            return $handler->handle($request);
        }

        // No application redirect of attacker-controlled paths/authority. The trusted edge alone
        // redirects safe navigation; HTTP mutations and invalid authority never reach Actions.
        return (new ResponseFactory())->createResponse(421);
    }
}
