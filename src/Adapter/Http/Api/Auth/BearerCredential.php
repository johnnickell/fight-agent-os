<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Auth;

use App\Adapter\Security\AccessToken\AccessJwt;
use App\Application\Failure\AuthenticationRequired;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Class BearerCredential
 *
 * Extracts one unambiguous header credential and rejects alternative transports
 */
final readonly class BearerCredential
{
    /**
     * Returns only the exact case-sensitive Bearer compact-token syntax
     */
    public function extract(ServerRequestInterface $request): string
    {
        $headers = $request->getHeader('Authorization');
        $rawHeader = $request->getServerParams()['HTTP_AUTHORIZATION'] ?? null;
        if (
            ($rawHeader !== null && $rawHeader !== ($headers[0] ?? null))
            ||
            count($headers) !== 1
            || strlen($headers[0]) > AccessJwt::MAX_TOKEN_BYTES + 7
            || preg_match('/\ABearer ([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)\z/D', $headers[0], $match) !== 1
        ) {
            throw new AuthenticationRequired('Unauthorized.');
        }

        // Inspect raw sources as well as framework-parsed fields; never consume the downstream body.
        $body = $request->getBody();
        if (!$body->isSeekable() || ($body->getSize() !== null && $body->getSize() > 16384)) {
            throw new AuthenticationRequired('Unauthorized.');
        }

        try {
            $position = $body->tell();
            $body->rewind();
            $rawBody = $body->read(16385);
            $body->seek($position);
        } catch (Throwable) {
            throw new AuthenticationRequired('Unauthorized.');
        }
        if (strlen($rawBody) > 16384) {
            throw new AuthenticationRequired('Unauthorized.');
        }

        $sources = [
            rawurldecode($request->getUri()->getQuery()),
            rawurldecode($request->getHeaderLine('Cookie')),
            rawurldecode($rawBody),
            json_encode([
                $request->getQueryParams(), $request->getCookieParams(),
                $request->getParsedBody(), json_decode($rawBody)
            ]) ?: ''
        ];
        foreach ($sources as $source) {
            if (
                preg_match('/(?:\bBearer\s|[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{43,})/i', $source) === 1
                || preg_match('/(?:\A|[?&;\s{"\[])(?:access_token|token|authorization)["\s]*[=:]/i', $source) === 1
            ) {
                throw new AuthenticationRequired('Unauthorized.');
            }
        }

        return $match[1];
    }
}
