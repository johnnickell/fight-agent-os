<?php

declare(strict_types=1);

namespace App\Application\Security\PasswordChange;

use App\Application\Failure\StateConflict;
use App\Application\Security\Password\PasswordSetting;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\User\Exception\PasswordChangeRejectedException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use SensitiveParameter;
use Throwable;

/**
 * Class ChangePassword
 *
 * Applies replacement policy before package-owned current proof and atomic session invalidation
 */
final readonly class ChangePassword
{
    /**
     * Constructs ChangePassword
     */
    public function __construct(private PasswordSetting $passwords, private AuthenticationService $authentication)
    {
    }

    /**
     * Changes only the authenticated owner's password without issuing replacement authority
     */
    public function change(
        UserId $authenticatedUserId,
        #[SensitiveParameter] string $currentPassword,
        #[SensitiveParameter] string $password
    ): void {
        if ($this->passwords->rejection($password) !== null) {
            throw new PasswordChangePolicyRejected();
        }
        try {
            $this->authentication->changePassword($authenticatedUserId, $currentPassword, $password);
        } catch (PasswordChangeRejectedException) {
            throw new StateConflict();
        } catch (Throwable) {
            // A post-commit event failure cannot safely be replayed.
            throw new PasswordChangeUnavailable();
        }
    }
}
