<?php

declare(strict_types=1);

namespace App\Domain\Security\Session\Query;

use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Repository\Pagination;
use InvalidArgumentException;

/**
 * Class ListOwnedSessions
 *
 * Requests one self-service session page with optional device presentation
 */
final readonly class ListOwnedSessions implements Query
{
    /**
     * Constructs ListOwnedSessions
     */
    public function __construct(
        public UserId $userId,
        public RefreshSessionId $currentSessionId,
        public int $page,
        public int $perPage
    ) {
        if ($page < 1 || $page > 1000 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid session pagination.');
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        if (
            count($data) !== 4
            || !is_string($data['userId'] ?? null)
            || !is_string($data['currentSessionId'] ?? null)
            || !is_int($data['page'] ?? null)
            || !is_int($data['perPage'] ?? null)
        ) {
            throw new InvalidArgumentException('Invalid owned-session query input.');
        }

        return new self(
            UserId::fromString($data['userId']),
            RefreshSessionId::fromString($data['currentSessionId']),
            $data['page'],
            $data['perPage']
        );
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'userId'           => $this->userId->toString(),
            'currentSessionId' => $this->currentSessionId->toString(),
            'page'             => $this->page,
            'perPage'          => $this->perPage
        ];
    }

    /**
     * Returns pagination without caller-selected ordering
     */
    public function pagination(): Pagination
    {
        return new Pagination($this->page, $this->perPage);
    }
}
