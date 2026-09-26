<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

use App\Adapter\Security\HmacCsrfProofs;
use App\Application\Security\CsrfProof;
use App\Application\Security\GetCsrfProof;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;

/**
 * Class CsrfBootstrapAction
 *
 * Maps the public CSRF cookie to one application query
 */
final readonly class CsrfBootstrapAction
{
    public const string COOKIE = '__Secure-agent_os_csrf';

    /**
     * Constructs CsrfBootstrapAction
     */
    public function __construct(private QueryBus $queries, private CsrfBootstrapResponder $responder)
    {
    }

    /**
     * Dispatches one CSRF bootstrap query and delegates presentation
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $nonce = null;
        $cookies = $request->getHeader('Cookie');
        if (count($cookies) > 1) {
            throw new HttpBadRequestException($request);
        }

        $found = false;
        foreach (explode(';', $cookies[0] ?? '') as $cookie) {
            $pair = explode('=', trim($cookie), 2);
            if ($pair[0] !== self::COOKIE) {
                continue;
            }

            if ($found || count($pair) !== 2) {
                throw new HttpBadRequestException($request);
            }

            $found = true;
            $nonce = HmacCsrfProofs::validNonce($pair[1]) ? $pair[1] : null;
        }

        $result = $this->queries->fetch(new GetCsrfProof($nonce));
        if (!$result instanceof CsrfProof) {
            throw new \UnexpectedValueException('Unexpected CSRF query result.');
        }

        return $this->responder->respond($result);
    }
}
