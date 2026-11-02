<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Users;

use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\Common\Domain\Repository\ResultSet;
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
     *
     * @param array{users: ResultSet<UserView>, roles: array<string, list<array{name: string, managed: bool}>>} $page
     */
    public function __construct(private array $page)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $users = $this->page['users'];
        $records = [];
        foreach ($users->records() as $user) {
            $id = $user->getUserId()->toString();
            $records[] = new UserData($user, $this->page['roles'][$id])->toArray();
        }

        return [
            'page'          => $users->page(),
            'per_page'      => $users->perPage(),
            'total_pages'   => $users->totalPages(),
            'total_records' => $users->totalRecords(),
            'records'       => $records
        ];
    }
}
