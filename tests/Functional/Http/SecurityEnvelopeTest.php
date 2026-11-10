<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use App\Adapter\Http\Middleware\SecurityHeaders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Proves uniform response isolation and deny-CORS through actual application routes
 */
final class SecurityEnvelopeTest extends TestCase
{
    /**
     * Supplies representative public, protected, private-tool and missing routes
     *
     * @return iterable<string, array{string}>
     */
    public static function routes(): iterable
    {
        foreach (
            ['/', '/login', '/activate', '/reset-password/complete', '/account/password',
                '/account/sessions', '/admin/invite-super-admin', '/swagger', '/api/v1/me',
                '/api/v1/auth/csrf', '/api/v1/documentation/bootstrap', '/api/v1/not-found', '/missing'] as $path
        ) {
            yield $path => [$path];
        }
    }

    /**
     * Applies restrictive headers to HTML, safe API failures and routing errors alike
     */
    #[DataProvider('routes')]
    public function test_that_every_response_has_the_security_envelope(string $path): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test'.$path);
        $response = $app->handle($request);

        // Metadata/provider failures remain contained too; this header contract grants no route authority.
        self::assertSame(SecurityHeaders::CSP, $response->getHeaderLine('Content-Security-Policy'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('same-origin', $response->getHeaderLine('Cross-Origin-Opener-Policy'));
        self::assertSame('same-origin', $response->getHeaderLine('Cross-Origin-Resource-Policy'));
        self::assertStringContainsString('camera=()', $response->getHeaderLine('Permissions-Policy'));
        self::assertFalse($response->hasHeader('Strict-Transport-Security'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
    }

    /**
     * Supplies unapproved browser origins and preflight inputs
     *
     * @return iterable<string, array{array<string, string|list<string>>}>
     */
    public static function foreignRequests(): iterable
    {
        yield 'foreign origin' => [['Origin' => 'https://foreign.test']];
        yield 'null origin' => [['Origin' => 'null']];
        yield 'malformed origin' => [['Origin' => 'https://agent-os.test/path']];
        yield 'multiple origins' => [['Origin' => ['https://agent-os.test', 'https://foreign.test']]];
        yield 'cross-site metadata' => [['Sec-Fetch-Site' => 'cross-site']];
        yield 'same-site is not same-origin' => [['Sec-Fetch-Site' => 'same-site']];
        yield 'credentialed preflight' => [
            ['Origin' => 'https://agent-os.test', 'Access-Control-Request-Method' => 'POST']
        ];
        yield 'preflight headers' => [['Access-Control-Request-Headers' => 'authorization']];
    }

    /**
     * Rejects foreign delivery before protected API dispatch without reflecting authority
     *
     * @param array<string, string|list<string>> $headers
     */
    #[DataProvider('foreignRequests')]
    public function test_that_cross_origin_api_delivery_is_denied(array $headers): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/me');
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $response = $app->handle($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringNotContainsString('foreign.test', (string) $response->getBody());
    }

    /**
     * Preserves independent authentication when Origin is absent or same-origin
     */
    public function test_that_same_origin_and_nonbrowser_reads_do_not_gain_authority(): void
    {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/me');
        foreach ([$request, $request->withHeader('Origin', 'https://agent-os.test')] as $candidate) {
            self::assertSame(401, $app->handle($candidate)->getStatusCode());
        }
    }
}
