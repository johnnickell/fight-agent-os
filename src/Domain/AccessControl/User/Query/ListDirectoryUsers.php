<?php

declare(strict_types=1);

namespace App\Domain\AccessControl\User\Query;

use Fight\Common\Domain\Messaging\Query\Query;
use InvalidArgumentException;

/**
 * Class ListDirectoryUsers
 *
 * Requests a bounded page of Users with their current Role presentation
 */
final readonly class ListDirectoryUsers implements Query
{
    /**
     * Constructs ListDirectoryUsers
     */
    public function __construct(public int $page, public int $perPage)
    {
        if ($page < 1 || $page > 1000 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid directory pagination.');
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self($data['page'], $data['per_page']);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['page' => $this->page, 'per_page' => $this->perPage];
    }
}
