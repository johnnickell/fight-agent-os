<?php

declare(strict_types=1);

namespace App\Application\AccessControl\User\View;

/**
 * Class UserDirectoryPage
 *
 * Exposes a safe directory page with canonical pagination metadata
 */
final readonly class UserDirectoryPage
{
    /**
     * Constructs UserDirectoryPage
     *
     * @phpstan-param list<DirectoryUser> $users
     */
    public function __construct(
        public int $page,
        public int $perPage,
        public int $totalPages,
        public int $totalRecords,
        public array $users
    ) {
    }
}
