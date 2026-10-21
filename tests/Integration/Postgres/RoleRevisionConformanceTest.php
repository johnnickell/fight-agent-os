<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\Repository\PostgresPermissionRepository;
use App\Adapter\Persistence\Repository\PostgresRoleRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\Test\AccessControl\Application\AccessControl\Role\Repository\RoleRevisionConformance;

/**
 * Class RoleRevisionConformanceTest
 *
 * Binds unchanged upstream persisted-state assertions to PostgreSQL and reopened physical connections
 */
final class RoleRevisionConformanceTest extends RoleRevisionConformance
{
    private Connection $connection;

    /**
     * Serializes registration and same-name validation against every Role writer in both winner orders
     */
    public function testRegistrationAndValidationFences(): void
    {
        $roles = $this->repository();
        $now = new DateTimeImmutable('2026-10-20T12:00:00Z');
        $role = \Fight\AccessControl\Domain\AccessControl\Role\Role::define(
            \Fight\AccessControl\Domain\AccessControl\Role\RoleId::generate(),
            \Fight\AccessControl\Domain\AccessControl\Role\RoleName::fromString('ROLE_RACE'),
            [],
            $now
        );
        $this->transaction(fn() => $roles->add($role));
        $other = DriverManager::getConnection($this->connection->getParams());
        $competing = new PostgresRoleRepository($other, new AuthorizationReferenceFences($other));
        try {
            $this->connection->beginTransaction();
            self::assertTrue($roles->validateCurrent($role));
            $other->beginTransaction();
            $other->executeStatement("SET LOCAL lock_timeout = '100ms'");
            try {
                $competing->replace($role, $role->renameCustom(
                    \Fight\AccessControl\Domain\AccessControl\Role\RoleName::fromString('ROLE_RENAMED'),
                    $now
                ));
                self::fail('A writer overtook held no-op validation.');
            } catch (\Doctrine\DBAL\Exception\DriverException $exception) {
                self::assertSame('55P03', $exception->getSQLState());
            }
            $other->rollBack();
            $this->connection->commit();
            $changed = $role->renameCustom(
                \Fight\AccessControl\Domain\AccessControl\Role\RoleName::fromString('ROLE_RENAMED'),
                $now
            );
            self::assertTrue($other->transactional(fn() => $competing->replace($role, $changed)));
            $user = \Fight\AccessControl\Domain\AccessControl\User\User::invite(
                \Fight\AccessControl\Domain\AccessControl\User\UserId::generate(),
                \Fight\Common\Domain\Value\Internet\EmailAddress::fromString('registration-race@example.test'),
                $now
            );
            $user->assignRole($role->getId(), $now);
            $users = new \App\Adapter\Persistence\Repository\PostgresUserRepository(
                $this->connection,
                new AuthorizationReferenceFences($this->connection),
                new \App\Adapter\Persistence\Locking\AuthenticationAuthorityFences($this->connection)
            );
            self::assertFalse($this->connection->transactional(fn() => $users->addWithRoleAssignments($user, [$role])));
            self::assertNull($users->getById($user->getId()));
            $this->connection->beginTransaction();
            self::assertTrue($users->addWithRoleAssignments($user, [$changed]));
            $other->beginTransaction();
            $other->executeStatement("SET LOCAL lock_timeout = '100ms'");
            try {
                $competing->remove($changed);
                self::fail('Removal overtook pending registration.');
            } catch (\Doctrine\DBAL\Exception\DriverException $exception) {
                self::assertSame('55P03', $exception->getSQLState());
            }
            $other->rollBack();
            $this->connection->commit();
            self::assertFalse($other->transactional(fn() => $competing->remove($changed)));
            $unassigned = \Fight\AccessControl\Domain\AccessControl\Role\Role::define(
                \Fight\AccessControl\Domain\AccessControl\Role\RoleId::generate(),
                \Fight\AccessControl\Domain\AccessControl\Role\RoleName::fromString('ROLE_REMOVED'),
                [],
                $now
            );
            $this->transaction(fn() => $roles->add($unassigned));
            self::assertTrue($other->transactional(fn() => $competing->remove($unassigned)));
            $orphan = clone $user;
            $orphan->removeRole($changed->getId(), $now);
            $orphan->assignRole($unassigned->getId(), $now);
            self::assertFalse($this->connection->transactional(
                fn() => $users->addWithRoleAssignments($orphan, [$unassigned])
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
    protected function repository(): RoleRepository
    {
        $this->open();
        $this->connection->executeStatement('TRUNCATE users, roles, permissions CASCADE');

        return new PostgresRoleRepository($this->connection, new AuthorizationReferenceFences($this->connection));
    }

    /**
     * @inheritDoc
     */
    protected function restart(RoleRepository $repository): RoleRepository
    {
        $this->connection->close();
        $this->open();

        return new PostgresRoleRepository($this->connection, new AuthorizationReferenceFences($this->connection));
    }

    /**
     * @inheritDoc
     */
    protected function permission(): PermissionId
    {
        $permission = Permission::define(
            PermissionId::generate(),
            PermissionName::fromString('CUSTOM_READ'),
            new DateTimeImmutable('2030-01-01T00:00:00Z')
        );
        $repository = new PostgresPermissionRepository(
            $this->connection,
            new AuthorizationReferenceFences($this->connection)
        );
        $this->transaction(fn() => $repository->add($permission));

        return $permission->getId();
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
