<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Application\Authorization\RoleMutationUnitOfWork;
use App\Domain\Authorization\RoleMutationAudit;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\Role\Event\CustomRoleCreated;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class RoleMutationUnitOfWorkTest
 */
final class RoleMutationUnitOfWorkTest extends TestCase
{
    /**
     * Requires audit before transaction completion and leaves no-op callbacks unaudited
     */
    public function testAuditBoundary(): void
    {
        $state = new class {
            public bool $active = false;
            public bool $completed = false;
        };
        $transaction = $this->createStub(TransactionalUnitOfWork::class);
        $transaction->method('isClosed')->willReturn(true);
        $transaction->method('commitTransactional')->willReturnCallback(static function ($operation) use ($state) {
            $state->active = true;
            try {
                $result = $operation();
                $state->completed = true;

                return $result;
            } finally {
                $state->active = false;
            }
        });
        $event = new CustomRoleCreated(
            UserId::generate(),
            RoleId::generate(),
            RoleName::fromString('ROLE_CUSTOM'),
            new DateTimeImmutable('2026-10-20T12:00:00Z')
        );
        $audit = $this->createMock(RoleMutationAudit::class);
        $audit->expects(self::once())->method('record')->with($event)->willReturnCallback(static function () use (
            $state
        ): void {
            self::assertTrue($state->active);
            self::assertFalse($state->completed);
            throw new RuntimeException('audit unavailable');
        });
        $boundary = new RoleMutationUnitOfWork($transaction, $audit);
        self::assertTrue($boundary->isClosed());
        try {
            $boundary->commitTransactional(static fn() => $event);
            self::fail('Audit failure acknowledged mutation.');
        } catch (RuntimeException) {
            self::assertFalse($state->completed);
        }
        self::assertNull($boundary->commitTransactional(static fn() => null));
        self::assertTrue($state->completed);
    }
}
