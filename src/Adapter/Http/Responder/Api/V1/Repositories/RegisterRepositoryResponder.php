<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Repositories;

use App\Application\Workspace\RegistrationView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class RegisterRepositoryResponder
 */
final readonly class RegisterRepositoryResponder
{
    /**
     * Constructs RegisterRepositoryResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns a committed original registration without implying new grants
     */
    public function respond(RegistrationView $view): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new RegistrationData($view)),
            201,
            ['Cache-Control' => 'no-store']
        );
    }
}
