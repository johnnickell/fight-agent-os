<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\SessionPresentation;

use App\Adapter\Persistence\PostgresAtomicOperation;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Application\Sessions\BrowserFamily;
use App\Application\Sessions\DeviceClass;
use App\Application\Sessions\DeviceDescriptor;
use App\Application\Sessions\SessionPresentation;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use InvalidArgumentException;

/**
 * Class PostgresSessionPresentation
 *
 * Persists only closed coarse labels keyed by authoritative package session identity
 */
final readonly class PostgresSessionPresentation implements SessionPresentation
{
    /**
     * Constructs PostgresSessionPresentation
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function capture(RefreshSessionId $id, DeviceDescriptor $device, DateTimeImmutable $expiresAt): void
    {
        PostgresAtomicOperation::execute($this->connection, fn() => $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO session_presentation (session_id, browser_family, device_class, expires_at)
VALUES (?, ?, ?, ?) ON CONFLICT (session_id) DO NOTHING
SQL,
            [$id->toString(), $device->browser->value, $device->device->value, RefreshSessionRecords::date($expiresAt)]
        ));
    }

    /**
     * @inheritDoc
     */
    public function descriptors(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        if (count($ids) > 100) {
            throw new InvalidArgumentException('Invalid session page.');
        }
        $rows = PostgresAtomicOperation::execute($this->connection, fn() => $this->connection->executeQuery(
            'SELECT session_id, browser_family, device_class FROM session_presentation WHERE session_id IN (?)',
            [$ids],
            [ArrayParameterType::STRING]
        )->fetchAllAssociative());
        $descriptors = [];
        foreach ($rows as $row) {
            $descriptors[(string) $row['session_id']] = new DeviceDescriptor(
                BrowserFamily::tryFrom((string) $row['browser_family']) ?? BrowserFamily::UNKNOWN,
                DeviceClass::tryFrom((string) $row['device_class']) ?? DeviceClass::UNKNOWN
            );
        }

        return $descriptors;
    }

    /**
     * @inheritDoc
     */
    public function prune(int $limit): int
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Invalid cleanup bound.');
        }

        return (int) $this->connection->executeStatement(
            <<<'SQL'
DELETE FROM session_presentation WHERE session_id IN (
    SELECT p.session_id FROM session_presentation p LEFT JOIN refresh_sessions s ON s.id = p.session_id
    WHERE p.expires_at <= CURRENT_TIMESTAMP OR s.id IS NULL OR s.revoked
        OR s.idle_expires_at <= CURRENT_TIMESTAMP OR s.absolute_expires_at <= CURRENT_TIMESTAMP
    ORDER BY p.expires_at, p.session_id LIMIT ?
)
SQL,
            [$limit],
            [ParameterType::INTEGER]
        );
    }
}
