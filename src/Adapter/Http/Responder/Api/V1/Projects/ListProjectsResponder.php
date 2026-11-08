<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Projects;

use App\Application\Workspace\Project\View\ProjectPage;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ListProjectsResponder
 */
final readonly class ListProjectsResponder
{
    /**
     * Constructs ListProjectsResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns bounded current facts
     */
    public function respond(ProjectPage $projectPage): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new ProjectPageData($projectPage)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
