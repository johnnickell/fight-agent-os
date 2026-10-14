<?php

declare(strict_types=1);

namespace App\Domain\Invitation\Command;

use App\Domain\Invitation\ConsoleInvitationInput;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Messaging\Command\Command;
use InvalidArgumentException;

/**
 * Class InviteOrdinaryUser
 *
 * Requests an atomic ordinary invitation attributed to a trusted operator
 */
final readonly class InviteOrdinaryUser implements Command
{
    /**
     * Constructs InviteOrdinaryUser
     */
    public function __construct(public ConsoleInvitationInput $input, public UserId $userId)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        if (
            array_keys($data) !== ['operator_id', 'email', 'user_id']
            || !is_string($data['operator_id']) || !is_string($data['email']) || !is_string($data['user_id'])
        ) {
            throw new InvalidArgumentException('Invalid ordinary invitation.');
        }

        return new self(
            new ConsoleInvitationInput($data['operator_id'], $data['email']),
            UserId::fromString($data['user_id'])
        );
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'operator_id' => $this->input->operatorId->toString(),
            'email'       => $this->input->email->toString(),
            'user_id'     => $this->userId->toString()
        ];
    }
}
