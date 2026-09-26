<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Guard;

use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * Class DatabaseTargetGuard
 */
final readonly class DatabaseTargetGuard
{
    /**
     * Constructs DatabaseTargetGuard
     *
     * @phpstan-param list<string> $allowedHosts
     */
    public function __construct(private array $allowedHosts)
    {
    }

    /**
     * Validates a destructive test target before connection
     *
     * @phpstan-return array{host: string, database: string, user: string}
     */
    public function assertConfigured(string $environment, string $databaseUrl): array
    {
        if ($environment !== 'test') {
            throw new RuntimeException('Destructive database operations require explicit test mode.');
        }

        $parts = parse_url($databaseUrl);
        if ($parts === false) {
            throw new RuntimeException('The test database target is invalid.');
        }

        $host = $parts['host'] ?? '';
        $user = isset($parts['user']) ? rawurldecode($parts['user']) : '';
        $database = isset($parts['path']) ? rawurldecode(ltrim($parts['path'], '/')) : '';

        if ($host === '' || !in_array($host, $this->allowedHosts, true)) {
            throw new RuntimeException('The test database host is not allowlisted.');
        }

        if (!str_ends_with($database, '_test') || $this->isKnownNonTestIdentity($database)) {
            throw new RuntimeException('The database name is not an approved test identity.');
        }

        if (!str_ends_with($user, '_test') || $this->isKnownNonTestIdentity($user)) {
            throw new RuntimeException('The database role is not an approved test identity.');
        }

        return ['host' => $host, 'database' => $database, 'user' => $user];
    }

    /**
     * Verifies the connected server identity before mutation
     *
     * @phpstan-param array{host: string, database: string, user: string} $expected
     */
    public function assertConnected(Connection $connection, array $expected): void
    {
        /** @var array{database_name: string, role_name: string, server_address: string|null}|false $actual */
        $actual = $connection->fetchAssociative(
            <<<'SQL'
SELECT current_database() AS database_name, current_user AS role_name,
    inet_server_addr()::text AS server_address
SQL
        );

        if (
            $actual === false
            || $actual['database_name'] !== $expected['database']
            || $actual['role_name'] !== $expected['user']
            || $actual['server_address'] === null
        ) {
            throw new RuntimeException('The connected database identity does not match the guarded test target.');
        }

        $allowedAddresses = gethostbynamel($expected['host']);
        $serverAddress = explode('/', $actual['server_address'], 2)[0];
        if ($allowedAddresses === false || !in_array($serverAddress, $allowedAddresses, true)) {
            throw new RuntimeException('The connected server address does not match the allowlisted test host.');
        }
    }

    /**
     * Detects a protected non-test database identity
     */
    private function isKnownNonTestIdentity(string $identity): bool
    {
        return in_array(strtolower($identity), [
            'agent_os',
            'development',
            'local',
            'postgres',
            'production',
            'staging'
        ], true);
    }
}
