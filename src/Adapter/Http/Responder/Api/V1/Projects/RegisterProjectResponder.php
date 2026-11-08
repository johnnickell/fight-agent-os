<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Projects;

use App\Domain\Workspace\Project\ProjectId;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class RegisterProjectResponder
 */
final readonly class RegisterProjectResponder
{
    /**
     * Constructs RegisterProjectResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns the entry-known identity without querying to manufacture a command result
     */
    public function respond(ProjectId $projectId): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new ProjectCreatedData($projectId)),
            201,
            ['Cache-Control' => 'no-store']
        );
    }
}
