<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Projects;

use App\Application\Workspace\Project\View\ProjectConflicts;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PreviewProjectConflictsResponder
 */
final readonly class PreviewProjectConflictsResponder
{
    /**
     * Constructs PreviewProjectConflictsResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns advisory kinds without the owning Project's identity
     */
    public function respond(ProjectConflicts $projectConflicts): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new ConflictPreviewData($projectConflicts)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
