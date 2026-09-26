<?php

declare(strict_types=1);

namespace App\Adapter\Persistence;

use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Throwable;

/**
 * Class ActivationGrantTransitions
 *
 * Validates proposed storage transitions by replaying package-owned aggregate operations
 */
final class ActivationGrantTransitions
{
    /**
     * Constructs ActivationGrantTransitions
     */
    private function __construct()
    {
    }

    /**
     * Checks pristine initial state and aggregate delivery ownership
     */
    public static function pristine(ActivationGrant $grant): bool
    {
        $delivery = $grant->getDelivery();

        return $grant->getRevision() === 0
            && $grant->isIssued()
            && $delivery->isPristine()
            && $delivery->getUserId()->equals($grant->getUserId())
            && $delivery->getExpiresAt() == $grant->getExpiresAt();
    }

    /**
     * Checks the exact next revision and a transition produced by package policy
     */
    public static function replacement(ActivationGrant $before, ActivationGrant $after): bool
    {
        $oldDelivery = $before->getDelivery();
        $newDelivery = $after->getDelivery();
        if (
            !$after->getId()->equals($before->getId())
            || !$after->getUserId()->equals($before->getUserId())
            || $after->getCredentialHash() !== $before->getCredentialHash()
            || $after->getExpiresAt() != $before->getExpiresAt()
            || !$newDelivery->getId()->equals($oldDelivery->getId())
            || !$oldDelivery->getUserId()->equals($before->getUserId())
            || !$newDelivery->getUserId()->equals($after->getUserId())
            || $newDelivery->getEmail()->canonical() !== $oldDelivery->getEmail()->canonical()
            || $newDelivery->getExpiresAt() != $oldDelivery->getExpiresAt()
            || $after->getRevision() !== $before->getRevision() + 1
        ) {
            return false;
        }

        try {
            if ($before->isIssued() && !$after->isIssued()) {
                if (!($after->isConsumed() xor $after->isRevoked())) {
                    return false;
                }
                $at = $after->isConsumed() ? $after->getConsumedAt() : $after->getRevokedAt();
                if (!$at instanceof DateTimeImmutable) {
                    return false;
                }
                $expected = $after->isConsumed() ? $before->consume($at) : $before->revoke($at);
            } elseif ($before->isIssued() && $after->isIssued()) {
                $expected = match ($newDelivery->getStatus()) {
                    CredentialDeliveryStatus::CLAIMED => $before->claimDelivery(
                        $newDelivery->getClaimToken(),
                        $newDelivery->getClaimedAt(), $newDelivery->getLeaseUntil()
                    ),
                    CredentialDeliveryStatus::RETRY_PENDING => $before->failDelivery(
                        $oldDelivery->getClaimToken(),
                        $newDelivery->getLastOutcomeAt(),
                        $newDelivery->getLastFailure()
                    ),
                    CredentialDeliveryStatus::PENDING => $before->requestDeliveryRetry(),
                    CredentialDeliveryStatus::DELIVERED => $before->confirmDelivery(
                        $oldDelivery->getClaimToken(),
                        $newDelivery->getLastOutcomeAt()
                    ),
                    CredentialDeliveryStatus::PERMANENT_FAILURE => $before->failDeliveryPermanently(
                        $oldDelivery->getClaimToken(),
                        $newDelivery->getLastOutcomeAt()
                    ),
                    CredentialDeliveryStatus::EXPIRED => self::expire($before, $after),
                    CredentialDeliveryStatus::INVALIDATED => null,
                };
            } else {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return $expected instanceof ActivationGrant && ActivationGrantRecords::same($expected, $after);
    }

    /**
     * Transitions an outstanding grant to expired
     */
    private static function expire(ActivationGrant $before, ActivationGrant $after): ActivationGrant
    {
        $oldDelivery = $before->getDelivery();
        $newDelivery = $after->getDelivery();
        if (
            $oldDelivery->getStatus() === CredentialDeliveryStatus::CLAIMED
            && $newDelivery->getLastOutcomeAt() instanceof DateTimeImmutable
            && $newDelivery->getLastFailure() !== null
        ) {
            return $before->failDelivery(
                $oldDelivery->getClaimToken(),
                $newDelivery->getLastOutcomeAt(),
                $newDelivery->getLastFailure()
            );
        }

        return $before->expireDeliveryAt($newDelivery->getExpiresAt());
    }
}
