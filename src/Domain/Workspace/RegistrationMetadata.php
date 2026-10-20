<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

/**
 * Class RegistrationMetadata
 *
 * Rejects ambiguous names rather than silently renaming a collaboration
 */
final readonly class RegistrationMetadata
{
    private const array RESERVED = [
        'admin', 'api', 'app', 'auth', 'new', 'null', 'repositories', 'system', 'workspace', 'workspaces'
    ];

    /**
     * Constructs RegistrationMetadata
     */
    public function __construct(public string $displayName, public string $slug)
    {
        if (
            !preg_match('//u', $displayName) || mb_strlen($displayName) < 1 || mb_strlen($displayName) > 120
            || strlen($displayName) > 480 || preg_match('/[\p{C}\p{Zl}\p{Zp}]/u', $displayName)
            || preg_match('/\A\s|\s\z/u', $displayName)
            || strlen($slug) > 80 || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $slug)
            || in_array($slug, self::RESERVED, true)
        ) {
            throw new RegistrationInputRejected();
        }
    }
}
