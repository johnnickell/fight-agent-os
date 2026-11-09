<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Application\Authorization\PreventAdministratorLockout;
use App\Domain\Authorization\EffectiveAdministrators;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Error\Exception\StateConflict;
use PHPUnit\Framework\TestCase;

/**
 * Proves the owned non-atomic lockout policy independently of HTTP permission admission
 */
final class PreventAdministratorLockoutTest extends TestCase
{
    /**
     * Allows a different target when the current query does not identify the final administrator
     */
    public function testOtherTargetCanBeDisabled(): void
    {
        $administrators = $this->createStub(EffectiveAdministrators::class);
        $administrators->method('isLast')->willReturn(false);
        $policy = new PreventAdministratorLockout($administrators);
        $policy->assertCanDisable(UserId::generate(), UserId::generate());
        $this->addToAssertionCount(1);
    }

    /**
     * Rejects the observed final administrator without claiming a concurrent reservation
     */
    public function testObservedLastAdministratorRejects(): void
    {
        $administrators = $this->createStub(EffectiveAdministrators::class);
        $administrators->method('isLast')->willReturn(true);
        $policy = new PreventAdministratorLockout($administrators);
        $this->expectException(StateConflict::class);
        $policy->assertCanDisable(UserId::generate(), UserId::generate());
    }

    /**
     * Rejects self-targeting before querying mutable administrator counts
     */
    public function testSelfTargetRejectsBeforeQuery(): void
    {
        $administrators = $this->createMock(EffectiveAdministrators::class);
        $administrators->expects(self::never())->method('isLast');
        $actorId = UserId::generate();
        $policy = new PreventAdministratorLockout($administrators);
        $this->expectException(StateConflict::class);
        $policy->assertCanDisable($actorId, $actorId);
    }
}
