<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Repositories;

use App\Application\Failure\AuthenticationRequired;
use App\Domain\Workspace\RegistrationInputRejected;
use App\Domain\Workspace\RegistrationSources;
use App\Domain\Workspace\RepositoryId;
use App\Domain\Workspace\SourceDescriptor;
use App\Domain\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use stdClass;

/**
 * Class RegistrationInput
 *
 * Maps checked transport fields to the closed descriptor union without granting authority
 */
final readonly class RegistrationInput
{
    /**
     * Returns verified invocation authentication supplied by the security middleware
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
     * Maps a canonical non-nil Workspace path identity
     */
    public static function workspace(ServerRequestInterface $request): WorkspaceId
    {
        $id = (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('workspace_id');
        self::requireIdentity($id);

        return WorkspaceId::fromString($id);
    }

    /**
     * Maps a canonical non-nil Repository path identity
     */
    public static function repository(ServerRequestInterface $request): RepositoryId
    {
        $id = (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('repository_id');
        self::requireIdentity($id);

        return RepositoryId::fromString($id);
    }

    /**
     * Maps bounded explicit descriptors after unique-key JSON parsing and package validation
     *
     * @param array<string, mixed> $input
     */
    public static function sources(array $input): RegistrationSources
    {
        $primary = $input['primary_source'] ?? null;
        $equivalent = $input['equivalent_sources'] ?? [];
        if (
            ($primary !== null && !$primary instanceof stdClass) || !is_array($equivalent)
            || !array_is_list($equivalent) || count($equivalent) > 7
        ) {
            throw new RegistrationInputRejected();
        }
        $sources = [];
        foreach ($equivalent as $source) {
            if (!$source instanceof stdClass) {
                throw new RegistrationInputRejected();
            }
            $sources[] = SourceDescriptor::fromInput((array) $source);
        }

        return new RegistrationSources(
            $primary === null ? null : SourceDescriptor::fromInput((array) $primary),
            $sources
        );
    }

    /**
     * Rejects aliases, nil values and noncanonical route identifiers
     */
    private static function requireIdentity(string $id): void
    {
        if (!preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $id)) {
            throw new RegistrationInputRejected();
        }
    }
}
