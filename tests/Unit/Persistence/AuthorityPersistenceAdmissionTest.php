<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\Repository\PostgresEffectiveAdministrators;
use App\Domain\Authorization\InstallationManagedPolicy;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects authority decisions when persistence facts cannot support a safe write
 */
final class AuthorityPersistenceAdmissionTest extends TestCase
{
    /**
     * Refuses a snapshot that could predate a concurrent authentication change
     */
    public function testAuthenticationWriteRequiresReadCommitted(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('fetchOne')->willReturn('repeatable read');
        $connection->expects(self::never())->method('executeQuery');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Authentication authority writes require READ COMMITTED isolation.');
        new AuthenticationAuthorityFences($connection)->hold(UserId::generate());
    }

    /**
     * Refuses to hold a transaction-scoped fence outside the authority transaction
     */
    public function testRoleReferencesRequireTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::never())->method('executeQuery');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Authorization reference fences require an enclosing transaction.');
        new AuthorizationReferenceFences($connection)->holdRoleReferences();
    }

    /**
     * Rejects a permission update whose read view could miss a newly granted reference
     */
    public function testPermissionReferencesRequireReadCommitted(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('fetchOne')->willReturn('repeatable read');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Permission reference writes require READ COMMITTED isolation.');
        new AuthorizationReferenceFences($connection)->holdPermissionReferences();
    }

    /**
     * Distinguishes last-administrator status from unknown authority
     *
     * @param array<string, bool>|false $row
     * @param boolean|null              $isLast
     */
    #[DataProvider('authorityRows')]
    public function testAdministratorDecision(array|false $row, ?bool $isLast): void
    {
        $target = UserId::generate();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')->with(self::anything(), [
            'role_id'   => InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID,
            'target_id' => $target->toString()
        ])->willReturn($row);
        $repository = new PostgresEffectiveAdministrators($connection);
        if ($isLast === null) {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('Managed administrator authority is unavailable.');
            $repository->isLast($target);
        } else {
            self::assertSame($isLast, $repository->isLast($target));
        }
    }

    /**
     * Supplies valid decisions and unavailable managed authority
     *
     * @return iterable<string, array{array<string, bool>|false, ?bool}>
     */
    public static function authorityRows(): iterable
    {
        yield 'last administrator' => [['authority_valid' => true, 'last_administrator' => true], true];
        yield 'another administrator exists' => [['authority_valid' => true, 'last_administrator' => false], false];
        yield 'authority unavailable' => [['authority_valid' => false, 'last_administrator' => false], null];
        yield 'no result' => [false, null];
    }
}
