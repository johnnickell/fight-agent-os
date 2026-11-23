<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Repository\PostgresRefreshSessionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PgSqlException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Repository\Pagination;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects refresh-session lookup, rotation and revocation
 */
final class PostgresRefreshSessionRepositoryTest extends TestCase
{
    /**
     * Rejects attempts to change a session outside its owning transaction
     */
    public function testRevocationRequiresTransaction(): void
    {
        $session = $this->session();
        $connection = $this->connection(false);
        $connection->expects(self::never())->method('executeStatement');

        $this->expectException(LogicException::class);
        $this->repository($connection)->replace($session, $session->revoke());
    }

    /**
     * Rejects a replacement that targets a different session even when both are valid domain objects
     */
    public function testRejectsReplacementForADifferentSession(): void
    {
        $connection = $this->connection();
        $connection->expects(self::never())->method('executeStatement');

        self::assertFalse($this->repository($connection)->replace($this->session(), $this->session()->revoke()));
    }

    /**
     * Can revoke a session without requiring a current refresh authority or appending a reused digest
     */
    public function testRevocationPreservesDigestAndTiming(): void
    {
        $session = $this->session();
        $connection = $this->connection();
        $connection->expects(self::never())->method('fetchOne');
        $connection->expects(self::never())->method('insert');
        $connection->expects(self::once())->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $fields) use ($session): int {
                self::assertStringNotContainsString('rotated_at', $sql);
                self::assertSame($session->getId()->toString(), $fields['expected_id']);
                self::assertFalse($fields['expected_revoked']);
                self::assertTrue($fields['replacement_revoked']);
                self::assertSame(1, $fields['replacement_revision']);
                self::assertSame($fields['expected_credential_digest'], $fields['replacement_credential_digest']);
                self::assertSame($fields['expected_idle_expires_at'], $fields['replacement_idle_expires_at']);

                return 1;
            }
        );

        self::assertTrue($this->repository($connection)->replace($session, $session->revoke()));
    }

    /**
     * Rechecks identity authority after taking the fence and rejects an obsolete session before writing
     */
    public function testRotationDeniesObsoleteAuthority(): void
    {
        $session = $this->session();
        $connection = $this->connection();
        $connection->expects(self::once())->method('executeQuery')->with(
            self::stringContains('pg_advisory_xact_lock'),
            self::callback(static fn (array $parameters): bool => $parameters[0] === $session->getUserId()->toString())
        )->willReturn($this->createStub(Result::class));
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturnCallback(
            static function (string $sql, array $parameters) use ($session): string|false {
                if ($sql === 'SHOW transaction_isolation') {
                    return 'read committed';
                }
                self::assertStringContainsString("state = 'active'", $sql);
                self::assertSame([$session->getUserId()->toString(), 3], $parameters);

                return false;
            }
        );
        $connection->expects(self::never())->method('executeStatement');
        $connection->expects(self::never())->method('insert');

        $this->expectException(RefreshSessionNotFoundException::class);
        $this->repository($connection)->replace($session, $this->rotated($session));
    }

    /**
     * Appends the old digest only after winning the snapshot comparison and rolls back a history collision
     */
    #[DataProvider('rotationOutcomes')]
    public function testRotationGuardsDigestHistory(bool $matched, bool $collision): void
    {
        $session = $this->session();
        $replacement = $this->rotated($session);
        $connection = $this->connection();
        $connection->expects(self::exactly($matched ? 3 : 2))->method('fetchOne')->willReturnCallback(
            static function (string $sql, array $parameters) use ($session): string|int {
                if ($sql === 'SHOW transaction_isolation') {
                    return 'read committed';
                }
                if (str_contains($sql, 'FROM users')) {
                    self::assertSame([$session->getUserId()->toString(), 3], $parameters);

                    return 1;
                }
                self::assertStringContainsString('FROM refresh_session_used_credentials', $sql);
                self::assertSame([$session->getId()->toString()], $parameters);

                return 2;
            }
        );
        $connection->expects(self::once())->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $fields) use ($session, $replacement, $matched): int {
                self::assertStringContainsString('credential_digest = :expected_credential_digest', $sql);
                self::assertStringContainsString('revision = :expected_revision', $sql);
                self::assertSame(0, $fields['expected_revision']);
                self::assertSame($session->getCredentialDigest(), $fields['expected_credential_digest']);
                self::assertSame($replacement->getCredentialDigest(), $fields['replacement_credential_digest']);
                self::assertSame('2026-10-21 12:05:00.123456+00:00', $fields['replacement_rotated_at']);

                return (int) $matched;
            }
        );
        $history = $connection->expects(self::exactly((int) $matched))->method('insert');
        if ($matched) {
            $history->with('refresh_session_used_credentials', [
                'refresh_session_id' => $session->getId()->toString(),
                'sequence'           => 2,
                'credential_digest'  => $session->getCredentialDigest()
            ]);
            if ($collision) {
                $history->willThrowException(new UniqueConstraintViolationException(
                    new PgSqlException('Synthetic used digest collision', '23505'),
                    null
                ));
            } else {
                $history->willReturn(1);
            }
        }
        $connection->expects(self::exactly((int) $collision))->method('rollbackSavepoint');

        self::assertSame($matched && !$collision, $this->repository($connection)->replace($session, $replacement));
    }

    /**
     * Supplies successful, stale and conflicting rotation outcomes
     *
     * @return iterable<string, array{bool, bool}>
     */
    public static function rotationOutcomes(): iterable
    {
        yield 'same session snapshot' => [true, false];
        yield 'session already changed' => [false, false];
        yield 'historical credential collision' => [true, true];
    }

    /**
     * Resolves the current and historical digest namespaces separately and loads the owning session
     */
    #[DataProvider('lookupCases')]
    public function testCredentialAndIdentityLookups(string $mode, bool $exists): void
    {
        $row = $this->storedRow();
        $old = RefreshCredential::fromString(str_repeat('ab', 32));
        $current = RefreshCredential::fromString(str_repeat('cd', 32));
        $id = RefreshSessionId::fromString($row['id']);
        $connection = $this->connection(false);
        $connection->expects(self::exactly($exists ? ($mode === 'historical' ? 3 : 2) : 1))->method('executeQuery')
            ->willReturnCallback(function (
                string $sql,
                array $parameters
            ) use (
                $mode,
                $exists,
                $row,
                $old,
                $current
            ): Result {
                $result = $this->createStub(Result::class);
                if (str_starts_with($sql, 'SELECT refresh_session_id ')) {
                    self::assertSame('historical', $mode);
                    self::assertSame(['digest' => $old->digest()], $parameters);
                    $result->method('fetchOne')->willReturn($exists ? $row['id'] : false);
                } elseif (str_contains($sql, 'ORDER BY sequence')) {
                    self::assertSame(['session_id' => $row['id']], $parameters);
                    $result->method('fetchFirstColumn')->willReturn([$old->digest()]);
                } else {
                    self::assertSame(
                        $mode === 'current' ? ['digest' => $current->digest()] : ['id' => $row['id']],
                        $parameters
                    );
                    $result->method('fetchAssociative')->willReturn($exists ? $row : false);
                }

                return $result;
            });
        $repository = $this->repository($connection);

        $loaded = match ($mode) {
            'historical' => $repository->getByUsedCredential($old),
            'current' => $repository->getByCredential($current),
            'id' => $repository->getById($id),
            default => throw new LogicException('Unknown lookup fixture.')
        };

        if (!$exists) {
            self::assertNull($loaded);

            return;
        }
        self::assertNotNull($loaded);
        self::assertTrue($id->equals($loaded->getId()));
        self::assertTrue($loaded->matchesCredential($current));
        self::assertTrue($loaded->matchesUsedCredential($old));
        self::assertTrue($loaded->isRemembered());
        self::assertFalse($loaded->isRevoked());
        self::assertSame(3, $loaded->getAuthenticationVersion());
        self::assertSame(1, $loaded->getRevision());
    }

    /**
     * Supplies present and absent results from each lookup namespace
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function lookupCases(): iterable
    {
        foreach (['historical', 'current', 'id'] as $mode) {
            yield $mode.' match' => [$mode, true];
            yield $mode.' absent' => [$mode, false];
        }
    }

    /**
     * Applies identical active-session eligibility to the page, its total and the unbounded read
     */
    #[DataProvider('activeListingModes')]
    public function testActiveListingUsesBothExpiryDeadlines(bool $paginated): void
    {
        $row = $this->storedRow();
        $owner = UserId::fromString($row['user_id']);
        $at = new DateTimeImmutable('2026-10-21T12:06:00.123456Z');
        $connection = $this->connection(false);
        $connection->expects(self::exactly($paginated ? 3 : 2))->method('executeQuery')
            ->willReturnCallback(function (string $sql, array $parameters) use ($row, $paginated): Result {
                $result = $this->createStub(Result::class);
                if (str_contains($sql, 'refresh_session_used_credentials')) {
                    self::assertSame(['session_id' => $row['id']], $parameters);
                    $result->method('fetchFirstColumn')->willReturn([RefreshCredential::fromString(
                        str_repeat('ab', 32)
                    )->digest()]);

                    return $result;
                }
                self::assertStringContainsString('revoked = FALSE', $sql);
                self::assertStringContainsString('idle_expires_at > :active_at', $sql);
                self::assertStringContainsString('absolute_expires_at > :active_at', $sql);
                self::assertSame([
                    'user_id'   => $row['user_id'],
                    'active_at' => '2026-10-21 12:06:00.123456+00:00'
                ], $parameters);
                if (str_starts_with($sql, 'SELECT COUNT(*)')) {
                    self::assertStringNotContainsString('LIMIT', $sql);
                    $result->method('fetchOne')->willReturn('4');
                } else {
                    $stableOrder = '/ORDER BY created_at(?: ASC)?, id(?: ASC)?(?: LIMIT|$)/';
                    self::assertMatchesRegularExpression($stableOrder, $sql);
                    self::assertSame($paginated, str_contains($sql, 'LIMIT 2 OFFSET 2'));
                    $result->method('fetchAllAssociative')->willReturn([$row]);
                }

                return $result;
            });
        $repository = $this->repository($connection);

        if ($paginated) {
            $page = $repository->getByUserId($owner, $at, new Pagination(2, 2));
            self::assertSame(4, $page->totalRecords());
            self::assertSame(2, $page->page());
            $records = iterator_to_array($page, false);
        } else {
            $records = $repository->getAllActiveByUserId($owner, $at);
        }

        self::assertCount(1, $records);
        self::assertSame($row['id'], $records[0]->getId()->toString());
        self::assertTrue($records[0]->isUsableAt($at));
    }

    /**
     * Supplies the paginated and unbounded list modes
     *
     * @return iterable<string, array{bool}>
     */
    public static function activeListingModes(): iterable
    {
        yield 'page with total' => [true];
        yield 'all active sessions' => [false];
    }

    /**
     * Supplies an independently mapped rotated row with PostgreSQL boolean and integer encodings
     *
     * @return array<string, string>
     */
    private function storedRow(): array
    {
        return [
            'id'                     => RefreshSessionId::generate()->toString(),
            'user_id'                => UserId::generate()->toString(),
            'credential_digest'      => RefreshCredential::fromString(str_repeat('cd', 32))->digest(),
            'created_at'             => '2026-10-21 12:00:00.123456+00:00',
            'last_activity_at'       => '2026-10-21 12:05:00.123456+00:00',
            'rotated_at'             => '2026-10-21 12:05:00.123456+00:00',
            'idle_expires_at'        => '2026-10-21 13:00:00.123456+00:00',
            'absolute_expires_at'    => '2026-10-22 12:00:00.123456+00:00',
            'authentication_version' => '3',
            'remembered'             => 't',
            'revision'               => '1',
            'revoked'                => 'f'
        ];
    }

    /**
     * Substitutes the database while retaining the real statement construction
     */
    private function connection(bool $inTransaction = true): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn($inTransaction);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->method('createQueryBuilder')->willReturnCallback(
            static fn (): QueryBuilder => new QueryBuilder($connection)
        );

        return $connection;
    }

    /**
     * Uses the authority fence on the same isolated connection
     */
    private function repository(Connection $connection): PostgresRefreshSessionRepository
    {
        return new PostgresRefreshSessionRepository($connection, new AuthenticationAuthorityFences($connection));
    }

    /**
     * Supplies an unrevoked session with fixed idle and absolute deadlines
     */
    private function session(): RefreshSession
    {
        $now = new DateTimeImmutable('2026-10-21T12:00:00.123456Z');

        return RefreshSession::start(
            RefreshSessionId::generate(),
            UserId::generate(),
            RefreshCredential::fromString(str_repeat('ab', 32)),
            $now,
            $now->modify('+1 hour'),
            $now->modify('+1 day'),
            3,
            true
        );
    }

    /**
     * Rotates the synthetic session to a different credential and idle deadline
     */
    private function rotated(RefreshSession $session): RefreshSession
    {
        return $session->rotate(
            RefreshCredential::fromString(str_repeat('cd', 32)),
            $session->getCreatedAt()->modify('+5 minutes'),
            $session->getIdleExpiresAt()->modify('+5 minutes')
        );
    }
}
