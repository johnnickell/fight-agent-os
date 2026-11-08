<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Projects;

use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\ProjectInputRejected;
use App\Domain\Workspace\Project\ProjectSources;
use App\Domain\Workspace\Project\SourceDescriptor;
use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use stdClass;

/**
 * Class ProjectInput
 *
 * Maps closed transport values without granting authority
 */
final readonly class ProjectInput
{
    /**
     * Returns middleware-verified invocation authentication
     */
    public static function authentication(ServerRequestInterface $request): AuthenticationContext
    {
        $authentication = $request->getAttribute(AuthenticationContext::class);
        if (!$authentication instanceof AuthenticationContext) {
            throw new AuthenticationRequired();
        }

        return $authentication;
    }

    /**
     * Maps a canonical non-nil Workspace identity
     */
    public static function workspace(ServerRequestInterface $request): WorkspaceId
    {
        return WorkspaceId::fromString(self::identity($request, 'workspace_id'));
    }

    /**
     * Maps a canonical non-nil Project identity
     */
    public static function project(ServerRequestInterface $request): ProjectId
    {
        return ProjectId::fromString(self::identity($request, 'project_id'));
    }

    /**
     * Maps explicit bounded source evidence
     *
     * @param array<string, mixed> $input
     */
    public static function sources(array $input): ProjectSources
    {
        $primary = $input['primary_source'] ?? null;
        $equivalent = $input['equivalent_sources'] ?? [];
        if (
            ($primary !== null && !$primary instanceof stdClass) || !is_array($equivalent)
            || !array_is_list($equivalent) || count($equivalent) > 7
        ) {
            throw new ProjectInputRejected();
        }
        $sources = [];
        foreach ($equivalent as $source) {
            if (!$source instanceof stdClass) {
                throw new ProjectInputRejected();
            }
            $sources[] = SourceDescriptor::fromInput((array) $source);
        }

        return new ProjectSources($primary === null ? null : SourceDescriptor::fromInput((array) $primary), $sources);
    }

    /**
     * Rejects aliases and noncanonical route identifiers
     */
    private static function identity(ServerRequestInterface $request, string $name): string
    {
        $id = (string) RouteContext::fromRequest($request)->getRoute()?->getArgument($name);
        if (!preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $id)) {
            throw new ProjectInputRejected();
        }

        return $id;
    }
}
