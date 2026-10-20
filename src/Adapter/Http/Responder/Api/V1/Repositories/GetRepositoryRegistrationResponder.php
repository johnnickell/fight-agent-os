<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Repositories;

use App\Application\Workspace\RegistrationView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GetRepositoryRegistrationResponder
 */
final readonly class GetRepositoryRegistrationResponder
{
    /**
     * Constructs GetRepositoryRegistrationResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns scoped facts without physical availability claims
     */
    public function respond(RegistrationView $view): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new RegistrationData($view)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
