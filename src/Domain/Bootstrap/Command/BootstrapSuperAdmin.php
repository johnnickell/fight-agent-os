<?php

declare(strict_types=1);

namespace App\Domain\Bootstrap\Command;

use App\Domain\Invitation\ConsoleInvitationInput;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Messaging\Command\Command;
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
    public function __construct(public ConsoleInvitationInput $input, public UserId $userId)
    {
        if ($input->operatorId->equals($userId)) {
            throw new InvalidArgumentException('Bootstrap operator and target must differ.');
        }
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
            throw new InvalidArgumentException('Invalid bootstrap input.');
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
