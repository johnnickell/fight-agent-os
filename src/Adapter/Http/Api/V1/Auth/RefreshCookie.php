<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Class RefreshCookie
 *
 * Reads only one exact opaque browser cookie without trusting parsed cookie parameters
 */
final readonly class RefreshCookie
{
    /**
     * Returns no authority for missing, ambiguous or malformed material
     */
    public function extract(ServerRequestInterface $request): ?string
    {
        $headers = $request->getHeader('Cookie');
        if (count($headers) !== 1 || strlen($headers[0]) > 4096) {
            return null;
        }
        $count = preg_match_all(
            '/(?:\A|;)\s*'.preg_quote(BrowserSessionCookies::REFRESH_NAME, '/').'=([^;]*)/',
            $headers[0],
            $matches
        );
        $value = $matches[1][0] ?? '';

        return $count === 1 && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1 ? $value : null;
    }
}
