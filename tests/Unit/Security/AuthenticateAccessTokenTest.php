<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Application\Failure\AuthenticationRequired;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\AccessTokens;

/**
 * Proves per-request authentication resolution without permission snapshots
 */
final class AuthenticateAccessTokenTest extends TestCase
{
    /**
     * Resolves current authority and never caches it between requests
     */
    public function testCurrentAuthorityAndOutage(): void
    {
        $fixture = new AccessTokens();
        $user = $this->user($fixture);
        $session = $this->session($fixture, $user);
        $claims = $fixture->claims();
        $claims['sub'] = $user->getId()->toString();
        $claims['sid'] = $session->getId()->toString();
        $token = $fixture->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::exactly(2))->method('getById')->willReturn($user);
        $sessions = $this->createMock(RefreshSessionRepository::class);
        $sessions->expects(self::exactly(2))->method('getById')->willReturn($session, $session->revoke());
        $authentication = new AuthenticateAccessToken($fixture->jwt, $users, $sessions, $fixture->clock);
        $context = $authentication->authenticate($token);
        self::assertTrue($context->getUserId()->equals($user->getId()));
        self::assertTrue($context->getRefreshSessionId()->equals($session->getId()));
        self::assertSame(1, $context->getAuthenticationVersion());
        $this->denied($authentication, $token);

        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willThrowException(new RuntimeException('SQL TOKEN KEY PRIVATE MARKER'));
        $this->denied(new AuthenticateAccessToken($fixture->jwt, $users, $sessions, $fixture->clock), $token);
    }

    /**
     * Rejects valid signatures with unusable package authority
     */
    #[DataProvider('authorityCases')]
    public function testAuthorityMatrix(string $case): void
    {
        $fixture = new AccessTokens();
        $user = $this->user($fixture);
        $session = $this->session($fixture, $user);
        $claims = $fixture->claims();
        $claims['sub'] = $user->getId()->toString();
        $claims['sid'] = $session->getId()->toString();
        if ($case === 'pending') {
            $user = $this->user($fixture, false);
            $claims['sub'] = $user->getId()->toString();
            $session = $this->session($fixture, $user);
            $claims['sid'] = $session->getId()->toString();
        } elseif ($case === 'disabled') {
            $user->disable($fixture->clock->now());
        } elseif ($case === 'deleted') {
            $user->delete($fixture->clock->now());
        } elseif ($case === 'stale-user') {
            $user->changePassword(
                PasswordHash::fromString((string) password_hash('new-secret', PASSWORD_BCRYPT)),
                $fixture->clock->now()
            );
        } elseif ($case === 'stale-token') {
            $claims['auth_version'] = 2;
        } elseif ($case === 'wrong-owner') {
            $session = $this->session($fixture, $this->user($fixture));
            $claims['sid'] = $session->getId()->toString();
        } elseif ($case === 'wrong-session') {
            $claims['sid'] = RefreshSessionId::generate()->toString();
        } elseif ($case === 'wrong-user') {
            $claims['sub'] = UserId::generate()->toString();
        } elseif ($case === 'revoked') {
            $session = $session->revoke();
        } elseif ($case === 'predates-session') {
            --$claims['iat'];
            --$claims['nbf'];
            --$claims['exp'];
        } elseif ($case === 'expired-idle' || $case === 'expired-absolute') {
            $session = $this->session($fixture, $user, $case);
            $claims['sid'] = $session->getId()->toString();
        } elseif ($case === 'missing-user') {
            $user = null;
        } elseif ($case === 'missing-session') {
            $session = null;
        }

        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturn($session);
        $authentication = new AuthenticateAccessToken($fixture->jwt, $users, $sessions, $fixture->clock);
        $this->denied($authentication, $fixture->signed(json_encode($claims, JSON_THROW_ON_ERROR)));
    }

    /**
     * Enumerates authority mismatches independently of signature validity
     *
     * @return iterable<string, array{string}>
     */
    public static function authorityCases(): iterable
    {
        $cases = [
            'pending', 'disabled', 'deleted', 'stale-user', 'stale-token', 'wrong-owner',
            'wrong-session', 'wrong-user', 'revoked', 'predates-session', 'expired-idle',
            'expired-absolute', 'missing-user', 'missing-session'
        ];
        foreach ($cases as $case) {
            yield $case => [$case];
        }
    }

    /**
     * Creates package-owned identity state
     */
    private function user(AccessTokens $fixture, bool $active = true): User
    {
        $user = User::invite(
            UserId::generate(),
            EmailAddress::fromString('active@example.test'),
            $fixture->clock->now()
        );
        if ($active) {
            $user->activate(
                PasswordHash::fromString((string) password_hash('secret', PASSWORD_BCRYPT)),
                $fixture->clock->now()
            );
        }

        return $user;
    }

    /**
     * Creates package-owned session state
     */
    private function session(AccessTokens $fixture, User $user, string $expiry = ''): RefreshSession
    {
        $now = $fixture->clock->now();
        $created = $expiry === '' ? $now : $now->modify('-1 hour');

        return RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $created,
            $expiry !== '' ? $now : $now->modify('+1 day'),
            $expiry === 'expired-absolute' ? $now : $now->modify('+2 days'),
            1,
            false
        );
    }

    /**
     * Requires a fixed failure with no store or credential diagnostics
     */
    private function denied(AuthenticateAccessToken $authentication, string $token): void
    {
        try {
            $authentication->authenticate($token);
            self::fail('Invalid authority accepted.');
        } catch (AuthenticationRequired $error) {
            self::assertSame('Unauthorized.', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
    }
}
