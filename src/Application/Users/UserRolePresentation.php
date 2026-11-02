<?php

declare(strict_types=1);

namespace App\Application\Users;

use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use LogicException;

/**
 * Class UserRolePresentation
 *
 * Resolves complete deterministic role presentation for safe user reads
 */
final readonly class UserRolePresentation
{
    /**
     * Constructs UserRolePresentation
     */
    public function __construct(private RoleRepository $roles)
    {
    }

    /**
     * Rejects missing, duplicate or aliased references instead of returning partial assignments
     *
     * @param iterable $records
     *
     * @phpstan-param iterable<UserView> $records
     *
     * @return array<string, list<array{name: string, managed: bool}>>
     */
    public function resolve(iterable $records): array
    {
        $expected = [];
        $users = [];
        foreach ($records as $user) {
            $key = $user->getUserId()->toString();
            if (isset($users[$key])) {
                throw new LogicException('User presentation is unavailable.');
            }
            $users[$key] = [];
            foreach ($user->getRoleIds() as $id) {
                $roleKey = $id->toString();
                if (isset($users[$key][$roleKey])) {
                    throw new LogicException('User role presentation is unavailable.');
                }
                $users[$key][$roleKey] = true;
                $expected[$roleKey] = $id;
            }
        }
        $resolved = [];
        $names = [];
        if ($expected !== []) {
            foreach ($this->roles->getByIds(array_values($expected)) as $role) {
                $key = $role->getId()->toString();
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
        }
        $presentation = [];
        foreach ($users as $key => $ids) {
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
