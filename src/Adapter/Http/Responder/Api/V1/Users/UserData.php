<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Users;

use DateTimeZone;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class UserData
 *
 * Maps the shared safe user field allowlist without serializing package internals
 */
final readonly class UserData implements Arrayable
{
    /**
     * Constructs UserData
     *
     * @param UserView $user
     * @param array $roles
     *
     * @phpstan-param list<array{name: string, managed: bool}> $roles
     */
    public function __construct(private UserView $user, private array $roles)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'user_id'    => $this->user->getUserId()->toString(),
            'email'      => $this->user->getEmail()->canonical(),
            'state'      => $this->user->getState()->value,
            'roles'      => $this->roles,
            'created_at' => $this->user->getCreatedAt()->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'updated_at' => $this->user->getUpdatedAt()->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM)
        ];
    }
}
