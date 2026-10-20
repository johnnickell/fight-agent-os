<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Authentication;

use App\Application\Failure\RateLimited;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use LogicException;
use Throwable;

/**
 * Class PostgresAuthAttempts
 *
 * Reuses independent advisory-locked admission with bounded rolling attempt histories
 */
final readonly class PostgresAuthAttempts
{
    /**
     * Constructs PostgresAuthAttempts
     */
    public function __construct(private Connection $connection, private Clock $clock, private int $capacity = 10000)
    {
        if ($capacity < 1 || $capacity > 100000) {
            throw new LogicException('Invalid authentication rate capacity.');
        }
    }

    /**
     * Checks mandatory storage availability without consuming an attempt
     */
    public function check(): void
    {
        try {
            $this->connection->executeQuery('SELECT key, hits, expires_at FROM auth_rate_limits LIMIT 0');
        } catch (Throwable) {
            throw new LogicException('Authentication admission unavailable.');
        }
    }

    /**
     * Records all dimensions together before business work or denies without retaining sensitive causes
     *
     * @param array<string, int> $budgets HMAC-only keys mapped to maximum attempts
     */
    public function consume(array $budgets, int $windowSeconds): void
    {
        if ($windowSeconds < 1 || $windowSeconds > 900 || $budgets === [] || count($budgets) > 2) {
            throw new LogicException('Invalid authentication admission budget.');
        }
        if ($this->connection->isTransactionActive()) {
            throw new LogicException('Authentication admission requires an independent transaction.');
        }
        try {
            $this->connection->transactional(function () use ($budgets, $windowSeconds): void {
                $this->connection->executeStatement("SET LOCAL lock_timeout = '2s'");
                $this->connection->executeStatement("SET LOCAL statement_timeout = '3s'");
                $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24121, 45)');
                $now = (int) $this->clock->now()->format('Uu');
                $cutoff = $now - $windowSeconds * 1000000;
                $this->connection->executeStatement('DELETE FROM auth_rate_limits WHERE expires_at <= ?', [$now]);
                $histories = [];
                $missing = 0;
                foreach ($budgets as $key => $limit) {
                    if (preg_match('/\A[0-9a-f]{64}\z/D', $key) !== 1 || $limit < 1 || $limit > 1000) {
                        throw new LogicException('Invalid authentication admission budget.');
                    }
                    $row = $this->connection->fetchOne('SELECT hits FROM auth_rate_limits WHERE key = ?', [$key]);
                    $missing += $row === false ? 1 : 0;
                    /** @var list<int> $hits */
                    $hits = $row === false ? [] : json_decode((string) $row, true, 16, JSON_THROW_ON_ERROR);
                    $hits = array_values(array_filter($hits, static fn(int $hit): bool => $hit > $cutoff));
                    if (count($hits) >= $limit) {
                        $retry = (int) ceil(($hits[0] - $cutoff) / 1000000);
                        throw new RateLimited(max(1, min(900, $retry)));
                    }
                    $histories[$key] = [...$hits, $now];
                }
                $count = (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits');
                if ($count + $missing > $this->capacity) {
                    throw new RateLimited(min(900, $windowSeconds));
                }
                foreach ($histories as $key => $hits) {
                    $this->connection->executeStatement(
                        <<<'SQL'
INSERT INTO auth_rate_limits (key, hits, expires_at) VALUES (?, ?::jsonb, ?)
ON CONFLICT (key) DO UPDATE SET hits = EXCLUDED.hits, expires_at = EXCLUDED.expires_at
SQL,
                        [$key, json_encode($hits, JSON_THROW_ON_ERROR), $now + $windowSeconds * 1000000]
                    );
                }
            });
        } catch (RateLimited $failure) {
            throw $failure;
        } catch (Throwable) {
            throw new RateLimited(60);
        }
    }

    /**
     * Returns no database configuration to diagnostic snapshots
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['connection' => '[redacted]'];
    }
}
