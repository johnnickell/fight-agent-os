<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Command;

use App\Domain\Authorization\TrustedOperator;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Value\Identifier\Uuid;
use InvalidArgumentException;

/**
 * Class AddUserRole
 *
 * Requests an additive assignment to an eligible role
 */
final readonly class AddUserRole implements Command
{
    /**
     * Constructs AddUserRole
     */
    public function __construct(
        public TrustedOperator $operator,
        public UserId $userId,
        public RoleId $roleId,
        public Uuid $invocationId
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        if (
            array_keys($data) !== ['operator_id', 'audit_actor_id', 'user_id', 'role_id', 'invocation_id']
            || !is_string($data['operator_id']) || !is_string($data['audit_actor_id'])
            || !is_string($data['user_id']) || !is_string($data['role_id']) || !is_string($data['invocation_id'])
        ) {
            throw new InvalidArgumentException('Invalid assignment input.');
        }

        return new self(
            new TrustedOperator(UserId::fromString($data['operator_id']), $data['audit_actor_id']),
            UserId::fromString($data['user_id']),
            RoleId::fromString($data['role_id']),
            Uuid::fromString($data['invocation_id'])
        );
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'operator_id'    => $this->operator->operatorId->toString(),
            'audit_actor_id' => $this->operator->auditActorId,
            'user_id'        => $this->userId->toString(),
            'role_id'        => $this->roleId->toString(),
            'invocation_id'  => $this->invocationId->toString()
        ];
    }
}
