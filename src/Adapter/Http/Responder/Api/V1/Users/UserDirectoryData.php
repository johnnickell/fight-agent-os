<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Users;

use App\Application\AccessControl\User\View\UserDirectoryPage;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class UserDirectoryData
 *
 * Crosses the HTTP boundary with an explicit field allowlist, never entity serialization
 */
final readonly class UserDirectoryData implements Arrayable
{
    /**
     * Constructs UserDirectoryData
     */
    public function __construct(private UserDirectoryPage $page)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $records = [];
        foreach ($this->page->users as $entry) {
            $roles = array_map(static fn(RoleView $role): array => [
                'name'    => $role->getName()->toString(),
                'managed' => $role->isManaged()
            ], $entry->roles);
            $records[] = new UserData($entry->user, $roles)->toArray();
        }

        return [
            'page'          => $this->page->page,
            'per_page'      => $this->page->perPage,
            'total_pages'   => $this->page->totalPages,
            'total_records' => $this->page->totalRecords,
            'records'       => $records
        ];
    }
}
