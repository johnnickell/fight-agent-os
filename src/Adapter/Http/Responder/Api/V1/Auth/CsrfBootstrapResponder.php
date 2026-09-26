<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Domain\Security\Csrf\Query\CsrfProofView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CsrfBootstrapResponder
 *
 * Presents the bootstrap proof without exposing the nonce or application result
 */
final readonly class CsrfBootstrapResponder
{
    /**
     * Constructs CsrfBootstrapResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns a no-store JSend proof and a new session cookie only when needed
     */
    public function respond(CsrfProofView $result): ResponseInterface
    {
        $headers = ['Cache-Control' => 'no-store'];
        if ($result->newNonce) {
            $headers['Set-Cookie'] = sprintf(
                '%s=%s; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict',
                CsrfCookie::NAME,
                $result->nonce
            );
        }

        return $this->responses->fromEnvelope(JSendEnvelope::success(new CsrfBootstrapData($result)), 200, $headers);
    }
}
