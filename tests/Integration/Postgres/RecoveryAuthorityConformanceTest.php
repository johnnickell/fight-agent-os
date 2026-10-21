<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\Repository\PostgresPasswordResetGrantRepository;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\Test\AccessControl\Application\AccessControl\PasswordResetGrant\Repository\RecoveryAuthorityConformance;

/**
 * Class RecoveryAuthorityConformanceTest
 *
 * Binds unchanged conformance assertions to actual User and reset rows on one PostgreSQL transaction
 */
final class RecoveryAuthorityConformanceTest extends RecoveryAuthorityConformance
{
    private Connection $connection;
    private User $user;

    /**
     * Verifies both User-first recovery writer orders and cancellation without reissue on enable
     */
    public function testUserRecoveryFenceAndLifecycle(): void
    {
        $now = new \DateTimeImmutable('2026-10-20T12:00:00Z');
        $user = User::invite(
            \Fight\AccessControl\Domain\AccessControl\User\UserId::generate(),
            \Fight\Common\Domain\Value\Internet\EmailAddress::fromString('fenced@example.test'),
            $now
        );
        $user->activate(\Fight\AccessControl\Domain\AccessControl\User\PasswordHash::fromString(
            (string) password_hash('fixture', PASSWORD_BCRYPT, ['cost' => 4])
        ), $now);
        $grants = $this->repository($user);
        $users = $this->users();
        $grant = \Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant::issue(
            $user->getId(),
            PasswordResetCredential::fromString('fixture'),
            $now,
            $now->modify('+1 hour'),
            $user->getEmail(),
            'fixture-ciphertext',
            $user->getRecoveryAuthority()
        );
        $other = DriverManager::getConnection($this->connection->getParams());
        $otherUsers = new PostgresUserRepository(
            $other,
            new AuthorizationReferenceFences($other),
            new AuthenticationAuthorityFences($other)
        );
        $disabled = clone $user;
        $disabled->disable($now);
        try {
            $this->connection->beginTransaction();
            self::assertTrue($users->validateRecoveryAuthority($user));
            $other->beginTransaction();
            $other->executeStatement("SET LOCAL lock_timeout = '100ms'");
            try {
                $otherUsers->replaceLifecycleState($user, $disabled);
                self::fail('Lifecycle mutation overtook admitted recovery.');
            } catch (\Doctrine\DBAL\Exception\DriverException $exception) {
                self::assertSame('55P03', $exception->getSQLState());
            }
            $other->rollBack();
            self::assertTrue($grants->add($grant));
            $this->connection->commit();
            self::assertTrue($other->transactional(fn() => $otherUsers->replaceLifecycleState($user, $disabled)));
            $cancelled = $grants->getById($grant->getId());
            self::assertTrue($cancelled->isRevoked());
            self::assertFalse($cancelled->getDelivery()->hasRecoverableMaterial());
            $staleUser = clone $user;
            self::assertFalse($this->connection->transactional(fn() => $users->validateRecoveryAuthority($staleUser)));
            self::assertSame([], $grants->findDue($now, 10));
            $enabled = clone $disabled;
            $enabled->enable($now);
            self::assertTrue($other->transactional(fn() => $otherUsers->replaceLifecycleState($disabled, $enabled)));
            self::assertTrue($grants->getById($grant->getId())->isRevoked());
            self::assertSame([], $grants->findDue($now, 10));
            $stale = \Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrant::issue(
                $user->getId(),
                PasswordResetCredential::fromString('next'),
                $now,
                $now->modify('+1 hour'),
                $user->getEmail(),
                'fixture-ciphertext',
                $user->getRecoveryAuthority()
            );
            self::assertFalse($this->connection->transactional(
                fn() => $grants->appendAfterTerminal($cancelled, $stale)
            ));
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            if ($other->isTransactionActive()) {
                $other->rollBack();
            }
            $other->close();
        }
    }

    /**
     * @inheritDoc
     */
    protected function repository(User $user): PasswordResetGrantRepository
    {
        $this->open();
        $this->connection->executeStatement('TRUNCATE users CASCADE');
        $this->user = $user;
        $this->transaction(fn() => $this->users()->add($user));

        return new PostgresPasswordResetGrantRepository($this->connection);
    }

    /**
     * @inheritDoc
     */
    protected function restart(PasswordResetGrantRepository $repository): PasswordResetGrantRepository
    {
        $this->connection->close();
        $this->open();
        $stored = $this->users()->getById($this->user->getId());
        self::assertNotNull($stored);
        self::assertTrue($this->user->getRecoveryAuthority()->equals($stored->getRecoveryAuthority()));
        self::assertEquals($this->user->getEmail(), $stored->getEmail());

        return new PostgresPasswordResetGrantRepository($this->connection);
    }

    /**
     * @inheritDoc
     */
    protected function transaction(callable $operation): void
    {
        $this->connection->transactional(static fn() => $operation());
    }

    /**
     * Closes each qualification connection
     */
    protected function tearDown(): void
    {
        $this->connection->close();
    }

    /**
     * Uses the actual User row mapper and shared fences
     */
    private function users(): PostgresUserRepository
    {
        return new PostgresUserRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection),
            new AuthenticationAuthorityFences($this->connection)
        );
    }

    /**
     * Opens only the guarded test database
     */
    private function open(): void
    {
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection(
            new DsnParser(['postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'])->parse($url)
        );
        $guard->assertConnected($this->connection, $expected);
    }
}
