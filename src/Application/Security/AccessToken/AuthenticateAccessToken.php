<?php

declare(strict_types=1);

namespace App\Application\Security\AccessToken;

use App\Application\Failure\AuthenticationRequired;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Auth\Security\TokenDecoder;
use SensitiveParameter;
use Throwable;

/**
 * Class AuthenticateAccessToken
 *
 * Resolves validated access claims against current package-owned authentication state
 */
final readonly class AuthenticateAccessToken
{
    /**
     * Constructs AuthenticateAccessToken
     */
    public function __construct(
        private TokenDecoder $tokens,
        private UserRepository $users,
        private RefreshSessionRepository $sessions,
        private Clock $clock
    ) {
    }

    /**
     * Creates request authentication without resolving roles or permissions
     */
    public function authenticate(#[SensitiveParameter] string $token): AuthenticationContext
    {
        try {
            $claims = $this->tokens->decode($token);
            $userId = UserId::fromString($claims['sub']);
            $sessionId = RefreshSessionId::fromString($claims['sid']);
            $session = $this->sessions->getById($sessionId);
            $user = $this->users->getById($userId);
            $now = $this->clock->now();
            if (
                $user === null
                || $session === null
                || !$user->getId()->equals($userId)
                || !$session->getId()->equals($sessionId)
                || !$session->getUserId()->equals($userId)
                || $user->getState() !== UserState::ACTIVE
                || !$session->isUsableAt($now)
                || $user->getAuthenticationVersion() !== $claims['auth_version']
                || $session->getAuthenticationVersion() !== $claims['auth_version']
                || $claims['iat'] < $session->getCreatedAt()->getTimestamp()
                || $now->getTimestamp() >= $claims['exp']
            ) {
                throw new AuthenticationRequired();
            }

            return new AuthenticationContext($userId, $sessionId, $claims['auth_version']);
        } catch (Throwable) {
            // Store outages and package failures are fail-closed, without an exception chain.
            throw new AuthenticationRequired('Unauthorized.');
        }
    }
}
