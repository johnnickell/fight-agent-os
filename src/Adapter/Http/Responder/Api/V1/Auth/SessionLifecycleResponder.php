<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use App\Adapter\Http\Api\Failure\FailureData;
use App\Adapter\Http\Api\V1\Auth\SessionResponseFailure;
use Fight\AccessControl\Application\AccessControl\User\Security\RefreshOutcome;
use Fight\AccessControl\Application\AccessControl\User\Security\RefreshResult;
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\ServerError;
use Fight\Common\Application\Error\Exception\ServiceUnavailable;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;
use Throwable;

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
    public function __construct(
        private SessionResponder $sessions,
        private JSendResponseFactory $responses,
        private SafeHttpDiagnostics $diagnostics,
        private SafeHttpErrorPolicy $httpErrorPolicy
    ) {
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

        return $this->responses->fromEnvelope(
            JSendEnvelope::fail(new FailureData('State conflict.')),
            409,
            ['Cache-Control' => 'no-store']
        );
    }

    /**
     * Clears ambient authority on terminal refresh without revealing why it ended
     */
    public function terminal(): ResponseInterface
    {
        try {
            $response = $this->responses->fromEnvelope(
                JSendEnvelope::fail(new FailureData('Authentication required.')),
                401,
                ['WWW-Authenticate' => 'Bearer']
            );

            return $this->sessions->clear($response);
        } catch (Throwable $failure) {
            return $this->unavailable($failure);
        }
    }

    /**
     * Ends browser authority idempotently without claiming a particular server revocation
     */
    public function loggedOut(): ResponseInterface
    {
        try {
            return $this->sessions->clear($this->responses->fromEnvelope(JSendEnvelope::success(null), 200));
        } catch (Throwable $failure) {
            return $this->unavailable($failure);
        }
    }

    /**
     * Clears browser authority while honestly leaving the server outcome unconfirmed
     */
    public function unavailable(Throwable $failure): ResponseInterface
    {
        // This direct transport outcome must clear cookies even when reporting fails. Unknown faults remain
        // server errors; only declared availability failures receive 503. Neither result implies rollback.
        $intent = $failure instanceof ServiceUnavailable ? $failure : new ServerError(previous: $failure);
        try {
            $this->diagnostics->report($intent);
            [$status, $envelope, $headers] = $this->httpErrorPolicy->present($intent);
        } catch (Throwable) {
            // One reporting attempt only; use the same supplier's nonrecursive safe fallback.
            [$status, $envelope, $headers] = $this->httpErrorPolicy->fallback();
        }

        try {
            return $this->sessions->clear($this->responses->fromEnvelope($envelope, $status, $headers));
        } catch (Throwable) {
            // Keep direct recovery bounded; the outer boundary retains deletion if this attempt also fails.
            // Reporting/projection failure above still gets this bounded rendering recovery, not another report.
            try {
                [$status, $envelope, $headers] = $this->httpErrorPolicy->fallback();

                return $this->sessions->clear($this->responses->fromEnvelope($envelope, $status, $headers));
            } catch (Throwable) {
                throw new SessionResponseFailure();
            }
        }
    }
}
