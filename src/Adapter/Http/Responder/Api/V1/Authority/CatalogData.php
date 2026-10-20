<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Authority;

use Fight\AccessControl\Domain\AccessControl\Permission\Query\PermissionView;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class CatalogData
 *
 * Maps only the approved transport fields from package Views
 */
final readonly class CatalogData implements Arrayable
{
    /**
     * Constructs CatalogData
     *
     * @param RoleView|PermissionView|ResultSet<RoleView>|ResultSet<PermissionView> $view
     */
    public function __construct(private RoleView|PermissionView|ResultSet $view)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        if (!$this->view instanceof ResultSet) {
            return $this->record($this->view);
        }
        $records = [];
        foreach ($this->view->records() as $record) {
            $records[] = $this->record($record);
        }

        return [
            'page'          => $this->view->page(),
            'per_page'      => $this->view->perPage(),
            'total_pages'   => $this->view->totalPages(),
            'total_records' => $this->view->totalRecords(),
            'records'       => $records
        ];
    }

    /**
     * Returns explicit fields without the broader package role permission membership
     *
     * @return array<string, string|bool>
     */
    private function record(RoleView|PermissionView $view): array
    {
        if ($view instanceof RoleView) {
            return [
                'role_id' => $view->getRoleId()->toString(),
                'name'    => $view->getName()->toString(),
                'managed' => $view->isManaged()
            ];
        }

        return [
            'permission_id' => $view->getPermissionId()->toString(),
            'name'          => $view->getName()->toString(),
            'tier'          => $view->getTier()->value,
            'managed'       => $view->isManaged()
        ];
    }
}
