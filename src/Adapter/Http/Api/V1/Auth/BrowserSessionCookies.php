<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

use DateTimeZone;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Security\TokenSet;
use LogicException;
use SensitiveParameter;

/**
 * Class BrowserSessionCookies
 *
 * Owns the fixed ADR cookie identity and authentication-boundary nonce lifecycle
 */
final readonly class BrowserSessionCookies
{
    public const string REFRESH_NAME = '__Secure-agent_os_refresh';

    private const string ATTRIBUTES = '; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict';
    private const string EXPIRED = '; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0';

    /**
     * Constructs BrowserSessionCookies
     */
    public function __construct(private Clock $clock)
    {
    }

    /**
     * Maps only opaque refresh material to a session or bounded persistent cookie
     */
    public function refresh(#[SensitiveParameter] TokenSet $tokens): string
    {
        $credential = $tokens->getRefreshCredential()->toString();
        $now = $this->clock->now();
        $deadline = $tokens->getRefreshExpiresAt();
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $credential) !== 1 || $deadline <= $now
            || $deadline > $now->add(new \DateInterval($tokens->isRemembered() ? 'P1Y' : 'P2D'))
        ) {
            throw new LogicException('Invalid refresh cookie input.');
        }
        $date = $deadline->setTimezone(new DateTimeZone('GMT'))->format('D, d M Y H:i:s').' GMT';
        $expiry = $tokens->isRemembered() ? '; Expires='.$date : '';

        return self::REFRESH_NAME.'='.$credential.self::ATTRIBUTES.$expiry;
    }

    /**
     * Replaces the nonce on successful login or activation without retaining it
     */
    public function rotateNonce(): string
    {
        return CsrfCookie::NAME.'='.bin2hex(random_bytes(32)).self::ATTRIBUTES;
    }

    /**
     * Returns expiry for the exact refresh and nonce identities on terminal logout or failure
     *
     * @return list<string>
     */
    public function delete(): array
    {
        return [
            self::REFRESH_NAME.'='.self::ATTRIBUTES.self::EXPIRED,
            CsrfCookie::NAME.'='.self::ATTRIBUTES.self::EXPIRED
        ];
    }
}
