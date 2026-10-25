<?php

declare(strict_types=1);

namespace App\Application\Security\PasswordReset;

use App\Application\Failure\StateConflict;
use App\Application\Security\Password\PasswordSetting;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Exception\PasswordResetRejectedException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
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
    public function __construct(private PasswordSetting $passwords, private AuthenticationService $authentication)
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
            $this->authentication->resetPassword($userId, $credential, $password);
        } catch (PasswordResetRejectedException) {
            throw new StateConflict();
        } catch (Throwable) {
            // Post-commit event failure is uncertain, never a reason to replay the mutation.
            throw new ResetUnavailable();
        }
    }
}
