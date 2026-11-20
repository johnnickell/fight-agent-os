<?php

declare(strict_types=1);

namespace App\Application\Security\PasswordReset;

use App\Application\Security\Password\PasswordSetting;
use Fight\AccessControl\Application\AccessControl\User\PasswordManagement;
use Fight\AccessControl\Domain\AccessControl\Audit\AnonymousId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\StateConflict;
use SensitiveParameter;
use Throwable;

/**
 * Class CompletePasswordReset
 *
 * Applies shared candidate acceptance before the package-owned atomic reset
 */
final readonly class CompletePasswordReset
{
    /**
     * Constructs CompletePasswordReset
     */
    public function __construct(private PasswordSetting $passwords, private PasswordManagement $passwordManagement)
    {
    }

    /**
     * Replaces password authority without issuing a session or duplicating grant policy
     */
    public function complete(
        UserId $userId,
        #[SensitiveParameter] string $credential,
        #[SensitiveParameter] string $password
    ): void {
        if ($this->passwords->rejection($password) !== null) {
            throw new ResetPasswordRejected();
        }
        try {
            $this->passwordManagement->resetPassword($userId, $credential, $password, AnonymousId::generate());
        } catch (AuthenticationRequired) {
            throw new StateConflict();
        } catch (Throwable) {
            // Post-commit event failure is uncertain, never a reason to replay the mutation.
            throw new ResetUnavailable();
        }
    }
}
