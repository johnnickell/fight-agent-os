<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Http\Responder\Api\V1\Auth\CsrfBootstrapResponder;
use App\Adapter\Security\HmacCsrfProofs;
use App\Domain\Security\Csrf\Query\CsrfProofView;
use App\Domain\Security\Csrf\Query\GetCsrfProof;
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
            if ($pair[0] !== CsrfCookie::NAME) {
                continue;
            }

            if ($found || count($pair) !== 2) {
                throw new HttpBadRequestException($request);
            }

            $found = true;
            $nonce = HmacCsrfProofs::validNonce($pair[1]) ? $pair[1] : null;
        }

        $result = $this->queries->fetch(new GetCsrfProof($nonce));
        if (!$result instanceof CsrfProofView) {
            throw new \UnexpectedValueException('Unexpected CSRF query result.');
        }

        return $this->responder->respond($result);
    }
}
