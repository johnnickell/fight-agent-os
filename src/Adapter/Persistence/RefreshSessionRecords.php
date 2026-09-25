<?php

declare(strict_types=1);

namespace App\Adapter\Persistence;

use App\Adapter\Persistence\Hydration\PersistedRefreshSession;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Maps refresh-session rows without leaking persistence records to consumers
 *
 * The locked package exposes no getter for historical credential digests, so the adapter maintains them
 * incrementally: a rotation appends the previous current digest exactly as `RefreshSession::rotate()` does.
 */
final class RefreshSessionRecords
{
    /**
     * Inserts one refresh session and its historical credential digests
     */
    public static function insert(Connection $connection, RefreshSession $session): void
    {
        $connection->insert('refresh_sessions', [
            'id' => $session->getId()->toString(),
            'user_id' => $session->getUserId()->toString(),
            'credential_digest' => $session->getCredentialDigest(),
            'created_at' => self::date($session->getCreatedAt()),
            'last_activity_at' => self::date($session->getLastActivityAt()),
            'rotated_at' => null,
            'idle_expires_at' => self::date($session->getIdleExpiresAt()),
            'absolute_expires_at' => self::date($session->getAbsoluteExpiresAt()),
            'authentication_version' => $session->getAuthenticationVersion(),
            'remembered' => $session->isRemembered(),
            'revision' => $session->getRevision(),
            'revoked' => $session->isRevoked(),
        ], [
            'remembered' => ParameterType::BOOLEAN,
            'revoked' => ParameterType::BOOLEAN,
        ]);
    }

    /**
     * Reconstitutes a refresh session from one stored row
     *
     * @param array<string, mixed> $row
     */
    public static function hydrate(Connection $connection, array $row): RefreshSession
    {
        $id = (string) $row['id'];

        return PersistedRefreshSession::reconstitute(
            RefreshSessionId::fromString($id),
            UserId::fromString((string) $row['user_id']),
            (string) $row['credential_digest'],
            self::usedDigests($connection, $id),
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['last_activity_at']),
            $row['rotated_at'] === null ? null : new DateTimeImmutable((string) $row['rotated_at']),
            new DateTimeImmutable((string) $row['idle_expires_at']),
            new DateTimeImmutable((string) $row['absolute_expires_at']),
            (int) $row['authentication_version'],
            self::boolean($row['remembered']),
            (int) $row['revision'],
            self::boolean($row['revoked'])
        );
    }

    /**
     * Appends the superseded credential digest exactly once to a session's history
     */
    public static function appendUsedDigest(Connection $connection, string $sessionId, string $digest): void
    {
        $sequence = (int) $connection->fetchOne(
            'SELECT COALESCE(MAX(sequence), -1) + 1 FROM refresh_session_used_credentials '
            . 'WHERE refresh_session_id = ?',
            [$sessionId]
        );

        $connection->insert('refresh_session_used_credentials', [
            'refresh_session_id' => $sessionId,
            'sequence' => $sequence,
            'credential_digest' => $digest,
        ]);
    }

    /**
     * Returns the ordered historical credential digests for a session
     *
     * @return list<string>
     */
    public static function usedDigests(Connection $connection, string $sessionId): array
    {
        $digests = $connection->createQueryBuilder()
            ->select('credential_digest')
            ->from('refresh_session_used_credentials')
            ->where('refresh_session_id = :session_id')
            ->setParameter('session_id', $sessionId)
            ->orderBy('sequence')
            ->fetchFirstColumn();

        return array_map(strval(...), $digests);
    }

    /**
     * Formats a timestamp for exact round-tripping
     */
    public static function date(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.uP');
    }

    private static function boolean(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't';
    }

    private function __construct()
    {
    }
}