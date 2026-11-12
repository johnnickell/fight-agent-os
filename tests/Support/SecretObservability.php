<?php

declare(strict_types=1);

namespace Tests\Support;

use Doctrine\DBAL\Connection;
use Monolog\Handler\TestHandler;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait SecretObservability
 *
 * Challenges normal journey output with actual invocation, persistence and configured secret material
 */
trait SecretObservability
{
    /** @var list<string> */
    private array $journeySecrets = [];
    private TestHandler $journeyLogs;

    /**
     * Configures actual journey diagnostic capture before constructing its HTTP pipeline
     */
    private function observeJourneyDiagnostics(\Fight\Common\Application\Service\Container $container): void
    {
        $this->journeyLogs = new TestHandler();
        $container->get('http.failure.logger')->setHandlers([$this->journeyLogs]);
    }

    /**
     * Scans real response, correlation, diagnostic records and required audit without printing secret values
     *
     * Successful access JSON and refresh cookies are intentional delivery channels, not diagnostics
     *
     * @param ResponseInterface $response
     * @param Connection $connection
     * @param TestHandler $logs
     * @param string[] $invocationSecrets
     *
     * @phpstan-param list<string> $invocationSecrets
     */
    private function assertSecretObservability(
        ResponseInterface $response,
        Connection $connection,
        TestHandler $logs,
        array $invocationSecrets
    ): void {
        $body = (string) $response->getBody();
        $decoded = null;
        if ($response->getHeaderLine('Content-Type') === 'application/json') {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            $body .= serialize($decoded);
        }
        $headers = serialize($response->getHeaders());
        $diagnostics = serialize($logs->getRecords());
        $audit = serialize($connection->fetchAllAssociative('SELECT * FROM audit_evidence'));
        $secrets = [...$this->journeySecrets, ...$invocationSecrets];
        foreach (
            ['users'                           => ['password_hash'],
            'refresh_sessions'                 => ['credential_digest'],
            'refresh_session_used_credentials' => ['credential_digest'],
            'activation_grants'                => ['credential_digest', 'delivery_ciphertext'],
            'password_reset_grants'            => ['credential_digest', 'delivery_ciphertext']] as $table => $columns
        ) {
            // Some owning failure cases deliberately remove authoritative storage before the request.
            if ($connection->fetchOne('SELECT to_regclass(?)', [$table]) === null) {
                continue;
            }
            foreach ($connection->fetchAllAssociative('SELECT '.implode(', ', $columns).' FROM '.$table) as $row) {
                foreach ($row as $value) {
                    if (is_string($value) && $value !== '') {
                        $secrets[] = $value;
                    }
                }
            }
        }
        foreach (
            ['APP_CSRF_MAC_KEY', 'APP_ACTIVATION_RATE_KEY', 'APP_AUTH_RATE_KEY',
            'APP_CREDENTIAL_DELIVERY_KEY'] as $name
        ) {
            $value = getenv($name);
            if (is_string($value) && $value !== '') {
                $secrets[] = $value;
            }
        }
        $configuration = json_decode(
            (string) getenv('APP_ACCESS_TOKEN_CONFIG'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        foreach ($configuration['keys'] as $key) {
            $secrets[] = $key['secret_hex'];
        }
        foreach (array_unique($secrets) as $secret) {
            if ($secret !== '') {
                foreach ([$body, $headers, $diagnostics, $audit] as $surface) {
                    self::assertFalse(
                        str_contains($surface, $secret),
                        'Private journey material reached observability.'
                    );
                }
            }
        }
        $delivered = $decoded['data']['access_token'] ?? null;
        if (is_string($delivered)) {
            foreach ([$headers, $diagnostics, $audit] as $surface) {
                self::assertFalse(str_contains($surface, $delivered), 'Access delivery escaped its JSON field.');
            }
        }
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            if (preg_match('/\A__Secure-agent_os_refresh=([a-f0-9]{64});/', $cookie, $match) === 1) {
                foreach ([$body, $diagnostics, $audit] as $surface) {
                    self::assertFalse(str_contains($surface, $match[1]), 'Refresh delivery escaped its cookie.');
                }
            }
        }
    }
}
