<?php

declare(strict_types=1);

namespace App\Application\Security\Activation;

use App\Application\Failure\StateConflict;
use App\Application\Security\Password\PasswordSetting;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Application\AccessControl\User\Security\TokenSet;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\ActivationGrantException;
use Fight\AccessControl\Domain\AccessControl\User\Exception\UserNotPendingActivationException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;
use SensitiveParameter;
use Throwable;

/**
 * Class ActivateInvitation
 *
 * Applies owned password acceptance before package-owned atomic activation
 */
final readonly class ActivateInvitation
{
    /**
     * Constructs ActivateInvitation
     */
    public function __construct(private PasswordSetting $passwords, private AuthenticationService $authentication)
    {
    }

    /**
     * Creates an ordinary session without reproducing grant or identity policy
     */
    public function activate(
        UserId $userId,
        #[SensitiveParameter] string $credential,
        #[SensitiveParameter] string $password
    ): TokenSet {
        $rejection = $this->passwords->rejection($password);
        if ($rejection !== null) {
            throw new ActivationPasswordRejected($rejection);
        }
        try {
            return $this->authentication->activate($userId, $credential, $password, false);
        } catch (LogicException | ActivationGrantException | UserNotPendingActivationException) {
            throw new StateConflict();
        } catch (Throwable) {
            // Neither the cause nor package diagnostics may retain secret-bearing arguments.
            throw new ActivationUnavailable();
        }
    }
}
