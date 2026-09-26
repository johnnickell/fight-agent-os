<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\Repository\PostgresRefreshSessionRepository;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\TestCase;

final class RefreshSessionRepositoryTest extends TestCase
{
    private Connection $connection;
    private DoctrineTransactionalUnitOfWork $unitOfWork;
    private PostgresRefreshSessionRepository $sessions;
    private PostgresUserRepository $users;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement(
            'TRUNCATE refresh_session_used_credentials, refresh_sessions, user_role_assignments, '
            . 'user_email_claims, users, role_permissions, roles, permissions CASCADE'
        );
        $this->sessions = new PostgresRefreshSessionRepository($this->connection);
        $this->users = new PostgresUserRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection),
            new AuthenticationAuthorityFences($this->connection)
        );
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $entityManager = new EntityManager($this->connection, $configuration);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);
    }

    public function test_session_round_trip_and_active_pagination(): void
    {
        $base = $this->clock('2026-10-01T13:00:00+00:00');
        [$user, $pairs] = $this->userWithSessions(3, $base);

        self::assertSessionEquals($pairs[0][0], $this->sessions->getById($pairs[0][0]->getId()));
        self::assertNull($this->sessions->getById(RefreshSessionId::generate()));

        $page = $this->sessions->getByUserId($user->getId(), $base->modify('+1 minute'), new Pagination(1, 2));
        self::assertSame(3, $page->totalRecords());
        self::assertCount(2, $page->records());

        $all = $this->sessions->getAllActiveByUserId($user->getId(), $base->modify('+1 minute'));
        self::assertCount(3, $all);
        self::assertSame($pairs[1][0]->getId()->toString(), $all[1]->getId()->toString());
    }

    public function test_current_and_used_credential_lookup_after_rotation(): void
    {
        $base = $this->clock('2026-10-01T13:00:00+00:00');
        [, $pairs] = $this->userWithSessions(1, $base);
        $original = $pairs[0][0];
        $originalCredential = $pairs[0][1];
        $newCredential = $this->credential();
        $replacement = $original->rotate($newCredential, $base->modify('+10 minutes'), $base->modify('+70 minutes'));

        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($original, $replacement)
        ));

        self::assertNotNull($this->sessions->getByCredential($newCredential));
        self::assertNull($this->sessions->getByCredential($originalCredential));

        $rehydrated = $this->sessions->getById($original->getId());
        self::assertNotNull($rehydrated);
        self::assertSame($replacement->getRevision(), $rehydrated->getRevision());
        self::assertTrue($rehydrated->matchesCredential($newCredential));
        self::assertTrue($rehydrated->matchesUsedCredential($originalCredential));
        self::assertSame(
            $replacement->getId()->toString(),
            $this->sessions->getByUsedCredential($originalCredential)?->getId()->toString()
        );
    }

    public function test_revocation_is_stored_and_excluded_from_active_queries(): void
    {
        $base = $this->clock('2026-10-01T13:00:00+00:00');
        [$user, $pairs] = $this->userWithSessions(2, $base);
        $revoked = $pairs[0][0]->revoke();

        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($pairs[0][0], $revoked)
        ));

        self::assertTrue($this->sessions->getById($pairs[0][0]->getId())?->isRevoked());
        self::assertCount(
            1,
            $this->sessions->getAllActiveByUserId($user->getId(), $base->modify('+1 minute'))
        );
        self::assertSame(
            1,
            $this->sessions->getByUserId($user->getId(), $base->modify('+1 minute'), new Pagination())
                ->totalRecords()
        );
    }

    public function test_expired_sessions_are_excluded_from_active_queries(): void
    {
        $base = $this->clock('2026-10-01T13:00:00+00:00');
        [$user, $pairs] = $this->userWithSessions(1, $base);

        self::assertCount(
            1,
            $this->sessions->getAllActiveByUserId($user->getId(), $base->modify('+30 minutes'))
        );
        self::assertCount(
            0,
            $this->sessions->getAllActiveByUserId($user->getId(), $base->modify('+2 hours'))
        );
        self::assertNotNull($this->sessions->getById($pairs[0][0]->getId()));
    }

    public function test_replace_rejects_stale_revision_and_advances_used_history(): void
    {
        $base = $this->clock('2026-10-01T13:00:00+00:00');
        [, $pairs] = $this->userWithSessions(1, $base);
        $original = $pairs[0][0];
        $replacement = $original->rotate($this->credential(), $base->modify('+10 minutes'), $base->modify('+70 minutes'));

        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($original, $replacement)
        ));
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($original, $replacement)
        ));

        $rotatedAgain = $replacement->rotate(
            $this->credential(),
            $base->modify('+20 minutes'),
            $base->modify('+80 minutes')
        );
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($replacement, $rotatedAgain)
        ));
        self::assertSame(
            2,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM refresh_session_used_credentials WHERE refresh_session_id = ?',
                [$original->getId()->toString()]
            )
        );
    }

    public function test_reused_credential_digest_is_rejected(): void
    {
        $base = $this->clock('2026-10-01T13:00:00+00:00');
        [, $pairs] = $this->userWithSessions(2, $base);
        $sharedCredential = $this->credential();
        $first = $pairs[0][0]->rotate(
            $sharedCredential,
            $base->modify('+10 minutes'),
            $base->modify('+70 minutes')
        );
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($pairs[0][0], $first)
        ));

        $second = $pairs[1][0]->rotate(
            $sharedCredential,
            $base->modify('+11 minutes'),
            $base->modify('+71 minutes')
        );
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($pairs[1][0], $second)
        ));
    }

    public function test_spent_credential_cannot_become_current_on_coupled_insert_or_rotation(): void
    {
        $base = $this->clock('2026-10-01T13:00:00+00:00');
        [$user, $pairs] = $this->userWithSessions(2, $base);
        [$first, $spent] = $pairs[0];
        [$second] = $pairs[1];
        $rotated = $first->rotate($this->credential(), $base->modify('+10 minutes'), $base->modify('+70 minutes'));
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($first, $rotated)
        ));

        $expectedUser = $this->users->getById($user->getId());
        self::assertNotNull($expectedUser);
        $replacementUser = clone $expectedUser;
        $replacementUser->advanceAuthenticationAuthorityRevision();
        $newSession = RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            $spent,
            $base->modify('+20 minutes'),
            $base->modify('+80 minutes'),
            $base->modify('+1 day'),
            $replacementUser->getAuthenticationVersion(),
            false
        );
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceAuthenticationAuthorityAndAddRefreshSession(
                $expectedUser,
                $replacementUser,
                $newSession
            )
        ));
        self::assertSame(
            $expectedUser->getAuthenticationAuthorityRevision(),
            $this->users->getById($user->getId())?->getAuthenticationAuthorityRevision()
        );
        self::assertNull($this->sessions->getById($newSession->getId()));

        $otherRotation = $second->rotate($spent, $base->modify('+21 minutes'), $base->modify('+81 minutes'));
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($second, $otherRotation)
        ));
        self::assertSame(
            $second->getCredentialDigest(),
            $this->sessions->getById($second->getId())?->getCredentialDigest()
        );
        self::assertNull($this->sessions->getByCredential($spent));
        self::assertSame(
            $first->getId()->toString(),
            $this->sessions->getByUsedCredential($spent)?->getId()->toString()
        );
    }

    public function test_session_cannot_rotate_back_to_its_own_spent_credential(): void
    {
        $base = $this->clock('2026-10-01T13:00:00+00:00');
        [, $pairs] = $this->userWithSessions(1, $base);
        [$original, $spent] = $pairs[0];
        $rotated = $original->rotate($this->credential(), $base->modify('+10 minutes'), $base->modify('+70 minutes'));
        self::assertTrue($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($original, $rotated)
        ));
        $reused = $rotated->rotate($spent, $base->modify('+20 minutes'), $base->modify('+80 minutes'));
        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->sessions->replace($rotated, $reused)
        ));
        self::assertSame(
            $rotated->getCredentialDigest(),
            $this->sessions->getById($original->getId())?->getCredentialDigest()
        );
        self::assertNull($this->sessions->getByCredential($spent));
        self::assertNotNull($this->sessions->getByUsedCredential($spent));
    }

    public function test_competing_digest_claims_wait_and_preserve_single_owner(): void
    {
        $base = $this->clock('2026-10-01T13:00:00+00:00');
        [$user, $pairs] = $this->userWithSessions(1, $base);
        [$original, $oldCredential] = $pairs[0];
        $shared = $this->credential();
        $rotated = $original->rotate($shared, $base->modify('+10 minutes'), $base->modify('+70 minutes'));

        $expectedUser = $this->users->getById($user->getId());
        self::assertNotNull($expectedUser);
        $replacementUser = clone $expectedUser;
        $replacementUser->advanceAuthenticationAuthorityRevision();
        $newSession = RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            $shared,
            $base->modify('+11 minutes'),
            $base->modify('+71 minutes'),
            $base->modify('+1 day'),
            $replacementUser->getAuthenticationVersion(),
            false
        );
        $competingConnection = $this->connection();
        $competingUsers = new PostgresUserRepository(
            $competingConnection,
            new AuthorizationReferenceFences($competingConnection),
            new AuthenticationAuthorityFences($competingConnection)
        );

        $this->connection->beginTransaction();
        $competingConnection->beginTransaction();
        $competingConnection->executeStatement("SET LOCAL lock_timeout = '100ms'");
        try {
            self::assertTrue($this->sessions->replace($original, $rotated));
            try {
                $competingUsers->replaceAuthenticationAuthorityAndAddRefreshSession(
                    $expectedUser,
                    $replacementUser,
                    $newSession
                );
                self::fail('A competing digest claim must wait for the first transaction.');
            } catch (DriverException) {
                self::assertTrue(true);
            }
            $this->connection->commit();
            $competingConnection->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($competingConnection->isTransactionActive()) {
                $competingConnection->rollBack();
            }
            $competingConnection->close();
        }

        self::assertFalse($this->unitOfWork->commitTransactional(
            fn(): bool => $this->users->replaceAuthenticationAuthorityAndAddRefreshSession(
                $expectedUser,
                $replacementUser,
                $newSession
            )
        ));
        self::assertSame(
            $expectedUser->getAuthenticationAuthorityRevision(),
            $this->users->getById($user->getId())?->getAuthenticationAuthorityRevision()
        );
        self::assertNull($this->sessions->getById($newSession->getId()));
        self::assertSame(
            $original->getId()->toString(),
            $this->sessions->getByCredential($shared)?->getId()->toString()
        );
        self::assertNull($this->sessions->getByUsedCredential($shared));
        self::assertNull($this->sessions->getByCredential($oldCredential));
        self::assertSame(
            $original->getId()->toString(),
            $this->sessions->getByUsedCredential($oldCredential)?->getId()->toString()
        );
    }

    public function test_competing_rotations_allow_one_winner(): void
    {
        $base = $this->clock('2026-10-01T13:00:00+00:00');
        [, $pairs] = $this->userWithSessions(1, $base);
        $expected = $pairs[0][0];
        $winnerCredential = $this->credential();
        $winner = $expected->rotate($winnerCredential, $base->modify('+10 minutes'), $base->modify('+70 minutes'));
        $loser = $expected->rotate($this->credential(), $base->modify('+11 minutes'), $base->modify('+71 minutes'));

        $competingConnection = $this->connection();
        $competingSessions = new PostgresRefreshSessionRepository($competingConnection);

        $this->connection->beginTransaction();
        $competingConnection->beginTransaction();
        $competingConnection->executeStatement("SET LOCAL lock_timeout = '100ms'");

        try {
            self::assertTrue($this->sessions->replace($expected, $winner));
            try {
                $competingSessions->replace($expected, $loser);
                self::fail('A competing rotation must wait on the authoritative session row.');
            } catch (DriverException) {
                self::assertTrue(true);
            }
            $this->connection->commit();
            $competingConnection->rollBack();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($competingConnection->isTransactionActive()) {
                $competingConnection->rollBack();
            }
            $competingConnection->close();
        }

        $stored = $this->sessions->getById($expected->getId());
        self::assertNotNull($stored);
        self::assertTrue($stored->matchesCredential($winnerCredential));
        self::assertSame(1, $stored->getRevision());
    }

    /**
     * @return array{User, list<array{0: RefreshSession, 1: RefreshCredential}>}
     */
    private function userWithSessions(
        int $count,
        DateTimeImmutable $base,
        bool $remembered = false
    ): array {
        $user = $this->activeUser('session-owner@example.test');
        $this->unitOfWork->commitTransactional(fn() => $this->users->add($user));
        $pairs = [];

        for ($index = 0; $index < $count; $index++) {
            $expected = $this->users->getById($user->getId());
            self::assertNotNull($expected);
            $replacement = clone $expected;
            $replacement->advanceAuthenticationAuthorityRevision();
            $createdAt = $base->modify(sprintf('+%d minutes', $index));
            $credential = $this->credential();
            $session = RefreshSession::start(
                RefreshSessionId::generate(),
                $replacement->getId(),
                $credential,
                $createdAt,
                $createdAt->modify('+1 hour'),
                $createdAt->modify('+1 day'),
                $replacement->getAuthenticationVersion(),
                $remembered
            );
            self::assertTrue($this->unitOfWork->commitTransactional(
                fn(): bool => $this->users->replaceAuthenticationAuthorityAndAddRefreshSession(
                    $expected,
                    $replacement,
                    $session
                )
            ));
            $pairs[] = [$session, $credential];
        }

        return [$user, $pairs];
    }

    private function activeUser(string $email): User
    {
        $now = $this->clock('2026-10-01T12:30:00+00:00');
        $user = User::invite(UserId::generate(), EmailAddress::fromString($email), $now);
        $user->activate(
            PasswordHash::fromString((string) password_hash('session-secret', PASSWORD_BCRYPT)),
            $now
        );

        return $user;
    }

    private function credential(): RefreshCredential
    {
        return RefreshCredential::fromString(bin2hex(random_bytes(32)));
    }

    private function clock(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }

    private function connection(): Connection
    {
        $databaseUrl = getenv('TEST_DATABASE_URL');
        $allowedHost = getenv('TEST_DATABASE_ALLOWED_HOST');
        self::assertIsString($databaseUrl);
        self::assertNotSame('', $databaseUrl);
        self::assertIsString($allowedHost);
        self::assertNotSame('', $allowedHost);

        $guard = new DatabaseTargetGuard(array_values(array_filter(array_map(
            'trim',
            explode(',', $allowedHost)
        ))));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $databaseUrl);
        $connection = DriverManager::getConnection((new DsnParser([
            'postgres' => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql',
        ]))->parse($databaseUrl));
        $guard->assertConnected($connection, $expected);

        return $connection;
    }

    private static function assertSessionEquals(RefreshSession $expected, ?RefreshSession $actual): void
    {
        self::assertNotNull($actual);
        self::assertSame($expected->getId()->toString(), $actual->getId()->toString());
        self::assertSame($expected->getUserId()->toString(), $actual->getUserId()->toString());
        self::assertSame($expected->getCredentialDigest(), $actual->getCredentialDigest());
        self::assertSame($expected->getAuthenticationVersion(), $actual->getAuthenticationVersion());
        self::assertSame($expected->isRemembered(), $actual->isRemembered());
        self::assertSame($expected->getRevision(), $actual->getRevision());
        self::assertSame($expected->isRevoked(), $actual->isRevoked());
        self::assertEquals($expected->getCreatedAt(), $actual->getCreatedAt());
        self::assertEquals($expected->getLastActivityAt(), $actual->getLastActivityAt());
        self::assertEquals($expected->getIdleExpiresAt(), $actual->getIdleExpiresAt());
        self::assertEquals($expected->getAbsoluteExpiresAt(), $actual->getAbsoluteExpiresAt());
    }
}
