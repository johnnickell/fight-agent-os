<?php

declare(strict_types=1);

namespace App\Domain\Invitation\Command;

use App\Domain\Invitation\ConsoleRoleInput;
use Fight\Common\Domain\Messaging\Command\Command;
use InvalidArgumentException;

/**
 * Class AddUserRole
 *
 * Requests eligible existing-role membership with trusted operator evidence
 */
final readonly class AddUserRole implements Command
{
    /**
     * Constructs AddUserRole
     */
    public function __construct(public ConsoleRoleInput $input)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        if (
            array_keys($data) !== ['operator_id', 'user_id', 'role_id']
            || !is_string($data['operator_id']) || !is_string($data['user_id']) || !is_string($data['role_id'])
        ) {
            throw new InvalidArgumentException('Invalid console role addition.');
        }

        return new self(new ConsoleRoleInput($data['operator_id'], $data['user_id'], $data['role_id']));
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'operator_id' => $this->input->operatorId->toString(),
            'user_id'     => $this->input->userId->toString(),
            'role_id'     => $this->input->roleId->toString()
        ];
    }
}
