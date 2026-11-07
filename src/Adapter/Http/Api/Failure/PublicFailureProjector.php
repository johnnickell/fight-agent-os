<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Failure;

use App\Adapter\Http\Responder\Api\V1\Invitations\InvitationIssuanceData;
use App\Application\Failure\IncompleteInvitation;
use App\Application\Failure\InvitationConfirmationRequired;
use App\Application\Failure\InvitationRecoveryUncertain;
use App\Application\Security\Activation\ActivationPasswordRejected;
use App\Application\Security\PasswordChange\PasswordChangePolicyRejected;
use App\Application\Security\PasswordReset\ResetPasswordRejected;
use App\Domain\Security\Password\PasswordStructure;
use Fight\Common\Adapter\Http\Error\SafeHttpMetadata;
use Fight\Common\Adapter\Http\Error\SafeHttpPresentation;
use Fight\Common\Adapter\Http\Error\SafeHttpProjector;
use Throwable;

/**
 * Class PublicFailureProjector
 *
 * Selects independently authored public data, never diagnostic exception fields
 */
final readonly class PublicFailureProjector implements SafeHttpProjector
{
    /**
     * @inheritDoc
     */
    public function project(Throwable $failure, int $status): ?SafeHttpPresentation
    {
        if ($status === 401) {
            return new SafeHttpPresentation(metadata: new SafeHttpMetadata(challenge: 'Bearer'));
        }
        if ($failure instanceof CurrentSessionRevocation) {
            return new SafeHttpPresentation(data: [
                'message' => 'Use Logout for the current session.',
                'intent'  => 'use_logout'
            ]);
        }
        if ($failure instanceof RegistrationInputFailure) {
            return new SafeHttpPresentation(data: [
                'fields' => ['input' => ['Invalid repository registration input.']]
            ]);
        }
        if ($failure instanceof InvitationConfirmationRequired) {
            return new SafeHttpPresentation(data: [
                'fields' => ['body.confirm_super_admin' => ['Explicit confirmation is required.']]
            ]);
        }
        if ($failure instanceof IncompleteInvitation) {
            return new SafeHttpPresentation(
                'Invitation completion is unconfirmed. Read invitation status before another operation.',
                (new InvitationIssuanceData($failure->outcome))->toArray()
            );
        }
        if ($failure instanceof InvitationRecoveryUncertain) {
            return new SafeHttpPresentation(
                'Recovery outcome uncertain. Read invitation status before another operation.'
            );
        }
        if (
            $failure instanceof ActivationPasswordRejected || $failure instanceof ResetPasswordRejected
            || $failure instanceof PasswordChangePolicyRejected
        ) {
            return new SafeHttpPresentation(data: ['fields' => ['body.password' => [PasswordStructure::GUIDANCE]]]);
        }

        return null;
    }
}
