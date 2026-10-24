<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ResetBrowserSession
 *
 * Selects cookie cleanup only by matching package session ownership, never reset authority
 */
final readonly class ResetBrowserSession
{
    /**
     * Constructs ResetBrowserSession
     */
    public function __construct(private RefreshCookie $cookie, private RefreshSessionRepository $sessions)
    {
    }

    /**
     * Matches current or recently rotated browser material without touching another identity
     */
    public function matches(ServerRequestInterface $request, UserId $target): bool
    {
        $credential = $this->cookie->extract($request);
        if ($credential === null) {
            return false;
        }
        $value = RefreshCredential::fromString($credential);
        $session = $this->sessions->getByCredential($value) ?? $this->sessions->getByUsedCredential($value);

        return $session !== null && $session->getUserId()->equals($target);
    }
}
