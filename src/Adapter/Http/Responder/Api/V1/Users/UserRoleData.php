<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Users;

use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use LogicException;

/**
 * Class UserRoleData
 *
 * Maps complete package Views into the endpoint's sorted role allowlist without fetching data
 */
final class UserRoleData
{
    /**
     * Rejects incomplete or ambiguous presentation instead of emitting partial user data
     *
     * @param iterable $users
     * @param array $roles
     *
     * @phpstan-param iterable<UserView> $users
     * @phpstan-param list<RoleView> $roles
     *
     * @return array<string, list<array{name: string, managed: bool}>>
     */
    public static function map(iterable $users, array $roles): array
    {
        $expected = [];
        $assignments = [];
        foreach ($users as $user) {
            $key = $user->getUserId()->toString();
            if (isset($assignments[$key])) {
                throw new LogicException('User presentation is unavailable.');
            }
            $assignments[$key] = [];
            foreach ($user->getRoleIds() as $id) {
                $roleKey = $id->toString();
                if (isset($assignments[$key][$roleKey])) {
                    throw new LogicException('User role presentation is unavailable.');
                }
                $assignments[$key][$roleKey] = true;
                $expected[$roleKey] = true;
            }
        }
        $resolved = [];
        $names = [];
        foreach ($roles as $role) {
            $key = $role->getRoleId()->toString();
            $name = $role->getName()->toString();
            if (!isset($expected[$key]) || isset($names[$name])) {
                throw new LogicException('User role presentation is unavailable.');
            }
            unset($expected[$key]);
            $names[$name] = true;
            $resolved[$key] = ['name' => $name, 'managed' => $role->isManaged()];
        }
        if ($expected !== []) {
            throw new LogicException('User role presentation is unavailable.');
        }
        $presentation = [];
        foreach ($assignments as $key => $ids) {
            $assigned = [];
            foreach (array_keys($ids) as $id) {
                $assigned[] = $resolved[$id];
            }
            usort($assigned, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
            $presentation[$key] = $assigned;
        }

        return $presentation;
    }
}
