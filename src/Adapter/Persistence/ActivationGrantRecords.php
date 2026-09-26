<?php

declare(strict_types=1);

namespace App\Adapter\Persistence;

use App\Adapter\Persistence\Hydration\PersistedActivationDelivery;
use App\Adapter\Persistence\Hydration\PersistedActivationGrant;
use DateTimeImmutable;
use DateTimeZone;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantId;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\EncryptedCredentialMaterial;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Class ActivationGrantRecords
 *
 * Maps complete activation generations without exposing database rows to consumers
 */
final class ActivationGrantRecords
{
    /**
     * Constructs ActivationGrantRecords
     */
    private function __construct()
    {
    }

    /**
     * Returns the persistence fields of one package-owned generation
     *
     * @return array<string, int|string|null>
     */
    public static function fields(ActivationGrant $grant): array
    {
        $delivery = $grant->getDelivery();

        return [
            'id'                       => $grant->getId()->toString(),
            'user_id'                  => $grant->getUserId()->toString(),
            'credential_digest'        => $grant->getCredentialHash(),
            'expires_at'               => self::date($grant->getExpiresAt()),
            'consumed_at'              => self::optionalDate($grant->getConsumedAt()),
            'revoked_at'               => self::optionalDate($grant->getRevokedAt()),
            'revision'                 => $grant->getRevision(),
            'delivery_id'              => $delivery->getId()->toString(),
            'delivery_email'           => $delivery->getEmail()->canonical(),
            'delivery_ciphertext'      => $delivery->getEncryptedMaterial()?->reveal(),
            'delivery_due_at'          => self::date($delivery->getDueAt()),
            'delivery_status'          => $delivery->getStatus()->value,
            'delivery_claim_token'     => $delivery->getClaimToken()?->toString(),
            'delivery_claimed_at'      => self::optionalDate($delivery->getClaimedAt()),
            'delivery_lease_until'     => self::optionalDate($delivery->getLeaseUntil()),
            'delivery_attempt_count'   => $delivery->getAttemptCount(),
            'delivery_last_attempt_at' => self::optionalDate($delivery->getLastAttemptAt()),
            'delivery_last_outcome_at' => self::optionalDate($delivery->getLastOutcomeAt()),
            'delivery_last_failure'    => $delivery->getLastFailure()?->value
        ];
    }

    /**
     * Checks every security-relevant field, including encrypted material and claim ownership
     */
    public static function same(ActivationGrant $left, ActivationGrant $right): bool
    {
        return self::fields($left) === self::fields($right);
    }

    /**
     * Reconstitutes one grant and its complete delivery state
     *
     * @param array<string, mixed> $row
     */
    public static function hydrate(array $row): ActivationGrant
    {
        $userId = UserId::fromString((string) $row['user_id']);
        $expiresAt = new DateTimeImmutable((string) $row['expires_at']);
        $delivery = PersistedActivationDelivery::reconstitute(
            ActivationDeliveryId::fromString((string) $row['delivery_id']),
            $userId,
            EmailAddress::fromString((string) $row['delivery_email']),
            $row['delivery_ciphertext'] === null ? null : EncryptedCredentialMaterial::fromString(
                (string) $row['delivery_ciphertext']
            ),
            $expiresAt,
            new DateTimeImmutable((string) $row['delivery_due_at']),
            CredentialDeliveryStatus::from((string) $row['delivery_status']),
            $row['delivery_claim_token'] === null ? null : CredentialDeliveryClaimToken::fromString(
                (string) $row['delivery_claim_token']
            ),
            self::readDate($row['delivery_claimed_at']),
            self::readDate($row['delivery_lease_until']),
            (int) $row['delivery_attempt_count'],
            self::readDate($row['delivery_last_attempt_at']),
            self::readDate($row['delivery_last_outcome_at']),
            $row['delivery_last_failure'] === null ? null : CredentialDeliveryFailure::from(
                (string) $row['delivery_last_failure']
            )
        );

        return PersistedActivationGrant::reconstitute(
            ActivationGrantId::fromString((string) $row['id']),
            $userId,
            (string) $row['credential_digest'],
            $expiresAt,
            $delivery,
            self::readDate($row['consumed_at']),
            self::readDate($row['revoked_at']),
            (int) $row['revision']
        );
    }

    /**
     * Formats a timestamp for exact PostgreSQL round-tripping
     */
    public static function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    /**
     * Parses an optional stored date
     */
    private static function optionalDate(?DateTimeImmutable $date): ?string
    {
        return $date === null ? null : self::date($date);
    }

    /**
     * Parses a required stored date
     */
    private static function readDate(mixed $date): ?DateTimeImmutable
    {
        return $date === null ? null : new DateTimeImmutable((string) $date);
    }
}
