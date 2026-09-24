<?php

declare(strict_types=1);

namespace Tests\Integration\Composition;

use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Permission\QueryHandler\ListPermissionsHandler;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\ListPermissions;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\PermissionView;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AccessControlQueryCompositionTest extends TestCase
{
    public function test_package_query_routes_to_package_handler_with_an_injected_capability(): void
    {
        $permission = Permission::define(
            PermissionId::fromString('018f7f44-5e8d-7c5b-9c7d-8a6e62fe47d1'),
            PermissionName::fromString('VIEW_DASHBOARD'),
            new DateTimeImmutable('2026-01-02T03:04:05+00:00')
        );
        $repository = new RecordingPermissionRepository($permission);

        /** @var Container $container */
        $container = require sprintf('%s/config/services.php', dirname(__DIR__, 3));
        $container->set(PermissionRepository::class, static fn (): PermissionRepository => $repository);

        self::assertInstanceOf(ListPermissionsHandler::class, $container->get(ListPermissionsHandler::class));

        /** @var QueryBus $queryBus */
        $queryBus = $container->get(QueryBus::class);
        $result = $queryBus->fetch(new ListPermissions(new Pagination(2, 25, ['name' => Pagination::ASC])));

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(2, $result->page());
        self::assertSame(25, $result->perPage());
        self::assertSame(1, $result->totalRecords());
        self::assertSame(1, $repository->queries);
        self::assertSame(2, $repository->pagination?->page());
        self::assertSame(25, $repository->pagination?->perPage());
        self::assertSame(['name' => Pagination::ASC], $repository->pagination?->orderings());

        $view = $result->records()->first();
        self::assertInstanceOf(PermissionView::class, $view);
        self::assertSame(
            [
                'permission_id' => '018f7f44-5e8d-7c5b-9c7d-8a6e62fe47d1',
                'name'          => 'VIEW_DASHBOARD',
                'tier'          => null,
                'managed'       => false,
            ],
            $view->toArray()
        );
    }
}

final class RecordingPermissionRepository implements PermissionRepository
{
    public int $queries = 0;
    public ?Pagination $pagination = null;

    public function __construct(private readonly Permission $permission)
    {
    }

    public function add(Permission $permission): void
    {
        throw new LogicException('Not used by the composition proof.');
    }

    public function getById(PermissionId $id): ?Permission
    {
        throw new LogicException('Not used by the composition proof.');
    }

    public function getByName(PermissionName $name): ?Permission
    {
        throw new LogicException('Not used by the composition proof.');
    }

    public function getByIds(array $ids): array
    {
        throw new LogicException('Not used by the composition proof.');
    }

    public function getAll(Pagination $pagination): ResultSet
    {
        ++$this->queries;
        $this->pagination = $pagination;
        $records = ArrayList::of(Permission::class);
        $records->add($this->permission);

        return new ResultSet($pagination->page(), $pagination->perPage(), 1, $records);
    }

    public function getManaged(): array
    {
        throw new LogicException('Not used by the composition proof.');
    }

    public function replace(Permission $expected, Permission $replacement): bool
    {
        throw new LogicException('Not used by the composition proof.');
    }

    public function remove(Permission $permission): bool
    {
        throw new LogicException('Not used by the composition proof.');
    }
}
