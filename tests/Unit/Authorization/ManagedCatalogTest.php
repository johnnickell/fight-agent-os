<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Adapter\Authorization\JsonManagedPolicy;
use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Proves complete-catalog admission and continuing installation defaults
 */
final class ManagedCatalogTest extends TestCase
{
    /**
     * Expands only Super Admin when a valid future definition is introduced
     */
    public function testFuturePermission(): void
    {
        [$permissions, $roles] = self::catalogs();
        $future = PermissionId::generate()->toString();
        $permissions[] = ['id' => $future, 'name' => 'FUTURE_CAPABILITY', 'tier' => 'ADMIN_SAFE'];
        $roles[] = [
            'id'          => RoleId::generate()->toString(),
            'name'        => 'ROLE_ADDITIONAL',
            'permissions' => []
        ];
        $policy = InstallationManagedPolicy::create($permissions, $roles);
        self::assertCount(3, $policy->getRoles()[0]->getPermissionIds());
        self::assertCount(26, $policy->getRoles()[1]->getPermissionIds());
        self::assertSame($future, $policy->getRoles()[1]->getPermissionIds()[25]->toString());
        self::assertSame([], $policy->getRoles()[2]->getPermissionIds());
        self::assertCount(25, $policy->getReferencedPermissionIds());
    }

    /**
     * Rejects invalid desired state before a supported reconciliation message can be constructed
     */
    #[DataProvider('invalidCatalogs')]
    public function testRejectsInvalidCatalog(string $defect): void
    {
        [$permissions, $roles] = self::catalogs();
        switch ($defect) {
            case 'missing permission':
                array_pop($permissions);
                break;
            case 'missing role':
                array_pop($roles);
                break;
            case 'empty':
                $permissions = [];
                break;
            case 'permission object':
                $permissions = ['unexpected' => $permissions[0]];
                break;
            case 'role object':
                $roles = ['unexpected' => $roles[0]];
                break;
            case 'scalar':
                $permissions[0] = 'invalid';
                break;
            case 'extra field':
                $permissions[0]['extra'] = 'invalid';
                break;
            case 'wrong type':
                $permissions[0]['name'] = 1;
                break;
            case 'invalid id':
                $permissions[0]['id'] = 'invalid';
                break;
            case 'invalid name':
                $permissions[0]['name'] = '';
                break;
            case 'invalid tier':
                $permissions[0]['tier'] = 'UNKNOWN';
                break;
            case 'duplicate id':
                $permissions[1]['id'] = $permissions[0]['id'];
                break;
            case 'duplicate name':
                $permissions[] = $permissions[0];
                break;
            case 'duplicate role':
                $roles[] = $roles[0];
                break;
            case 'missing memberships':
                unset($roles[0]['permissions']);
                $roles[0]['other'] = [];
                break;
            case 'unknown membership':
                $roles[0]['permissions'][] = 'UNKNOWN';
                break;
            case 'untyped membership':
                $roles[0]['permissions'][] = [];
                break;
            case 'duplicate membership':
                $roles[0]['permissions'][] = 'VIEW_DASHBOARD';
                break;
            case 'user wildcard':
                $roles[0]['permissions'] = '*';
                break;
            case 'user expansion':
                $roles[0]['permissions'][] = 'READ_PROJECTS';
                break;
            case 'user identity':
                $roles[0]['id'] = $roles[1]['id'];
                break;
            case 'super identity':
                $roles[1]['id'] = $roles[0]['id'];
                break;
            case 'super list':
                $roles[1]['permissions'] = [];
                break;
            case 'protected custom':
                $roles[] = [
                    'id'          => RoleId::generate()->toString(),
                    'name'        => 'ROLE_CUSTOM',
                    'permissions' => ['READ_SWAGGER']
                ];
                break;
        }
        $this->expectException(Throwable::class);
        InstallationManagedPolicy::create($permissions, $roles);
    }

    /**
     * Supplies independent unsafe input categories
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidCatalogs(): iterable
    {
        foreach (
            [
            'missing permission', 'missing role', 'empty', 'permission object', 'role object', 'scalar',
            'extra field', 'wrong type', 'invalid id', 'invalid name', 'invalid tier', 'duplicate id',
            'duplicate name', 'duplicate role', 'missing memberships', 'unknown membership', 'untyped membership',
            'duplicate membership', 'user wildcard', 'user expansion', 'user identity', 'super identity',
            'super list', 'protected custom'
            ] as $defect
        ) {
            yield $defect => [$defect];
        }
    }

    /**
     * Converts the qualified baseline to disposable inputs rather than editing production catalogs
     *
     * @return array{list<array<string, mixed>>, list<array<string, mixed>>}
     */
    public static function catalogs(): array
    {
        $policy = (new JsonManagedPolicy())->load();
        $names = [];
        foreach ($policy->getPermissions() as $permission) {
            $names[$permission->getId()->toString()] = $permission->getName()->toString();
        }
        $roles = [];
        foreach ($policy->getRoles() as $role) {
            $roles[] = [
                'id'          => $role->getId()->toString(),
                'name'        => $role->getName()->toString(),
                'permissions' => $role->getName()->toString() === 'ROLE_SUPER_ADMIN' ? '*' : array_map(
                    static fn(PermissionId $id): string => $names[$id->toString()],
                    $role->getPermissionIds()
                )
            ];
        }

        return [$policy->toArray()['permissions'], $roles];
    }
}
