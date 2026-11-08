<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Projects;

use App\Application\Workspace\Project\View\ProjectView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GetProjectResponder
 */
final readonly class GetProjectResponder
{
    /**
     * Constructs GetProjectResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns current safe facts without fetching or granting physical authority
     */
    public function respond(ProjectView $projectView): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new ProjectData($projectView)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
