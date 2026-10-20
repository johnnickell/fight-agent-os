<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Repositories;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PreviewRepositoryConflictsResponder
 */
final readonly class PreviewRepositoryConflictsResponder
{
    /**
     * Constructs PreviewRepositoryConflictsResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns advisory kinds without another registration's identity
     *
     * @param array $conflicts
     *
     * @phpstan-param list<string> $conflicts
     */
    public function respond(array $conflicts): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new ConflictPreviewData($conflicts)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
