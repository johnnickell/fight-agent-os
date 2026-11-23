<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPermissionDefinition;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPermissionPlanItem;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyChangeAction;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyPlan;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Protects installation reconciliation admission
 */
final class InstallationManagedPolicyTest extends TestCase
{
    /**
     * Rejects a complete preview that still needs a managed definition written
     */
    public function testMatchingPreviewWithPendingChangeIsNotReconciled(): void
    {
        $definition = new ManagedPermissionDefinition(
            PermissionId::generate(),
            PermissionName::fromString('VIEW_DASHBOARD'),
            PermissionTier::ADMIN_SAFE
        );
        $policy = new ManagedPolicy([$definition], [], [$definition->getId()]);
        $pending = new ManagedPermissionPlanItem($definition, ManagedPolicyChangeAction::CREATE);
        $plan = new ManagedPolicyPlan([$pending], []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Managed installation authority requires reconciliation.');
        InstallationManagedPolicy::assertReconciled($plan, $policy);
    }
}
