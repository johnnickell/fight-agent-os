<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use Fight\AccessControl\Application\AccessControl\User\Security\RefreshOutcome;
use Fight\AccessControl\Application\AccessControl\User\Security\RefreshResult;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;

/**
 * Class SessionLifecycleResponder
 *
 * Maps secretless conflict, terminal and uncertain outcomes without disclosing session existence
 */
final readonly class SessionLifecycleResponder
{
    /**
     * Constructs SessionLifecycleResponder
     */
    public function __construct(private SessionResponder $sessions, private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns a winner's access view or one bounded recoverable conflict without cookie changes
     */
    public function respond(#[SensitiveParameter] RefreshResult $result): ResponseInterface
    {
        $tokens = $result->getTokenSet();
        if ($result->getOutcome() === RefreshOutcome::ROTATED && $tokens !== null) {
            return $this->sessions->refreshed($tokens);
        }

        return $this->responses->fromEnvelope(JSendEnvelope::error('Conflict.'), 409, ['Cache-Control' => 'no-store']);
    }

    /**
     * Clears ambient authority on terminal refresh without revealing why it ended
     */
    public function terminal(): ResponseInterface
    {
        return $this->sessions->clear($this->responses->fromEnvelope(JSendEnvelope::error('Unauthorized.'), 401));
    }

    /**
     * Ends browser authority idempotently without claiming a particular server revocation
     */
    public function loggedOut(): ResponseInterface
    {
        return $this->sessions->clear($this->responses->fromEnvelope(JSendEnvelope::success(null), 200));
    }

    /**
     * Clears browser authority while honestly leaving the server outcome unconfirmed
     */
    public function unavailable(): ResponseInterface
    {
        return $this->sessions->clear($this->responses->fromEnvelope(
            JSendEnvelope::error('Internal server error.'),
            500
        ));
    }
}
