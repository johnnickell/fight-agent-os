<?php

declare(strict_types=1);

namespace App\Domain\Invitation\Command;

use App\Domain\Invitation\InvitationMode;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;

/**
 * Class IssueInvitation
 *
 * Requests an admitted managed invitation and separate membership confirmation
 */
final readonly class IssueInvitation implements Command
{
    /**
     * Constructs IssueInvitation
     */
    public function __construct(
        public UserId $actorId,
        public UserId $userId,
        public EmailAddress $email,
        public InvitationMode $mode
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        if (
            !is_string($data['actor_id'] ?? null) || !is_string($data['user_id'] ?? null)
            || !is_string($data['email'] ?? null) || !is_string($data['mode'] ?? null)
        ) {
            throw new InvalidArgumentException('Invalid invitation intent.');
        }

        return new self(
            UserId::fromString($data['actor_id']),
            UserId::fromString($data['user_id']),
            EmailAddress::fromString($data['email']),
            InvitationMode::from($data['mode'])
        );
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'actor_id' => $this->actorId->toString(),
            'user_id'  => $this->userId->toString(),
            'email'    => $this->email->toString(),
            'mode'     => $this->mode->value
        ];
    }
}
