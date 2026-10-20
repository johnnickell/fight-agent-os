<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Repositories;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GenerateExceptionalReferenceResponder
 */
final readonly class GenerateExceptionalReferenceResponder
{
    /**
     * Constructs GenerateExceptionalReferenceResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns no reservation or creation authority
     */
    public function respond(string $reference): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new ExceptionalReferenceData($reference)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
