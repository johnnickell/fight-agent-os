<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\PermissionDenied;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext;
use Fight\AccessControl\Domain\AccessControl\Authorization\AuthenticatedUserPrincipal;
use Fight\AccessControl\Domain\AccessControl\Authorization\PrincipalPermission;
use Fight\AccessControl\Domain\AccessControl\Authorization\PrincipalRole;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves conjunctive exact permission decisions without role-name bypasses
 */
final class PermissionGuardTest extends TestCase
{
    /**
     * Checks each required permission against selected current authority
     *
     * @param string[] $granted
     * @param string[] $required
     * @param boolean  $allowed
     *
     * @phpstan-param list<string> $granted
     * @phpstan-param list<string> $required
     */
    #[DataProvider('decisions')]
    public function testDecisions(array $granted, array $required, bool $allowed): void
    {
        $principal = new AuthenticatedUserPrincipal(UserId::generate(), RefreshSessionId::generate(), 1, [
            new PrincipalRole(RoleId::generate(), RoleName::fromString('ROLE_SUPER_ADMIN'))
        ], array_map(static fn(string $name): PrincipalPermission => new PrincipalPermission(
            PermissionId::generate(),
            PermissionName::fromString($name)
        ), $granted));
        if (!$allowed) {
            $this->expectException(PermissionDenied::class);
        }
        $context = new SecurityContext($principal);
        new PermissionGuard()->assertAllowed($context, array_map(
            PermissionName::fromString(...),
            $required
        ));
        if ($allowed) {
            self::assertSame($principal, $context->getAuthenticatedAuthority());
        }
    }

    /**
     * Supplies independent single and conjunctive decision cases
     *
     * @return iterable<string, array{list<string>, list<string>, bool}>
     */
    public static function decisions(): iterable
    {
        yield 'authenticated only' => [[], [], true];
        yield 'single allowed' => [['READ_USERS'], ['READ_USERS'], true];
        yield 'single refused despite role' => [[], ['READ_USERS'], false];
        yield 'conjunction allowed' => [['READ_USERS', 'READ_ROLES'], ['READ_USERS', 'READ_ROLES'], true];
        yield 'second missing' => [['READ_USERS'], ['READ_USERS', 'READ_ROLES'], false];
        yield 'first missing' => [['READ_ROLES'], ['READ_USERS', 'READ_ROLES'], false];
        yield 'exact not prefix' => [['READ_USERS'], ['READ_USER'], false];
    }
}
