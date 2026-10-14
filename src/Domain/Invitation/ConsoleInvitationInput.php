<?php

declare(strict_types=1);

namespace App\Domain\Invitation;

use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;

/**
 * Class ConsoleInvitationInput
 *
 * Bounds explicit trusted operator identity and canonical invitation input
 */
final readonly class ConsoleInvitationInput
{
    public UserId $operatorId;
    public EmailAddress $email;

    /**
     * Constructs ConsoleInvitationInput
     */
    public function __construct(string $operatorId, string $email)
    {
        if (
            preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $operatorId) !== 1
            || strlen($email) > 254 || preg_match('/[\x00-\x20\x7f-\xff]/', $email) === 1
        ) {
            throw new InvalidArgumentException('Invalid console invitation input.');
        }

        $this->operatorId = UserId::fromString($operatorId);
        $this->email = EmailAddress::fromString(strtolower($email));
    }

    /**
     * Requires the exact ordinary outcome before commit or success presentation
     */
    public function requireOrdinaryOutcome(?UserView $view, UserId $userId): void
    {
        if (
            $view === null || !$view->getUserId()->equals($userId)
            || $view->getEmail()->toString() !== $this->email->toString()
            || $view->getState() !== UserState::PENDING_ACTIVATION
            || array_map(static fn($role): string => $role->toString(), $view->getRoleIds()) !== [
                InstallationManagedPolicy::USER_ROLE_ID
            ]
        ) {
            throw new LogicException('The ordinary invitation outcome was not established.');
        }
    }

    /**
     * Returns the trusted console actor rather than an authenticated user
     */
    public function actorId(): string
    {
        return 'console:'.$this->operatorId->toString();
    }
}
