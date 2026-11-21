<?php

declare(strict_types=1);

namespace App\Domain\Bootstrap\Command;

use App\Domain\Authorization\TrustedOperator;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;

/**
 * Class BootstrapSuperAdmin
 *
 * Requests one deliberately confirmed trusted installation bootstrap
 */
final readonly class BootstrapSuperAdmin implements Command
{
    /**
     * Constructs BootstrapSuperAdmin
     */
    public function __construct(
        public TrustedOperator $operator,
        public EmailAddress $email,
        public UserId $userId
    ) {
        if ($operator->operatorId->equals($userId)) {
            throw new InvalidArgumentException('Bootstrap operator and target must differ.');
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        if (
            array_keys($data) !== ['operator_id', 'audit_actor_id', 'email', 'user_id']
            || !is_string($data['operator_id']) || !is_string($data['audit_actor_id'])
            || !is_string($data['email']) || !is_string($data['user_id'])
        ) {
            throw new InvalidArgumentException('Invalid bootstrap input.');
        }

        return new self(
            new TrustedOperator(UserId::fromString($data['operator_id']), $data['audit_actor_id']),
            EmailAddress::fromString($data['email']),
            UserId::fromString($data['user_id'])
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
            'email'          => $this->email->toString(),
            'user_id'        => $this->userId->toString()
        ];
    }
}
