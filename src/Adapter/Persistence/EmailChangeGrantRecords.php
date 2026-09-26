<?php

declare(strict_types=1);

namespace App\Adapter\Persistence;

use App\Adapter\Persistence\Hydration\PersistedEmailChangeDelivery;
use App\Adapter\Persistence\Hydration\PersistedEmailChangeGrant;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\EncryptedCredentialMaterial;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDeliveryId;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrant;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Class EmailChangeGrantRecords
 *
 * Maps package-owned email-change authority and encrypted delivery state
 */
final class EmailChangeGrantRecords
{
    /**
     * Constructs EmailChangeGrantRecords
     */
    private function __construct()
    {
    }

    /**
     * Returns the stored state without a raw credential
     *
     * @return array<string, int|string|null>
     */
    public static function fields(EmailChangeGrant $grant): array
    {
        $delivery = $grant->getDelivery();

        return [
            'id'                       => $grant->getId()->toString(),
            'user_id'                  => $grant->getUserId()->toString(),
            'credential_digest'        => $grant->getCredentialHash(),
            'expires_at'               => self::date($grant->getExpiresAt()),
            'consumed_at'              => self::optional($grant->getConsumedAt()),
            'revoked_at'               => self::optional($grant->getRevokedAt()),
            'expired_at'               => self::optional($grant->getExpiredAt()),
            'revision'                 => $grant->getRevision(),
            'delivery_id'              => $delivery->getId()->toString(),
            'delivery_email'           => $delivery->getEmail()->canonical(),
            'delivery_ciphertext'      => $delivery->getEncryptedMaterial()?->reveal(),
            'delivery_expires_at'      => self::date($delivery->getExpiresAt()),
            'delivery_due_at'          => self::date($delivery->getDueAt()),
            'delivery_status'          => $delivery->getStatus()->value,
            'delivery_claim_token'     => $delivery->getClaimToken()?->toString(),
            'delivery_claimed_at'      => self::optional($delivery->getClaimedAt()),
            'delivery_lease_until'     => self::optional($delivery->getLeaseUntil()),
            'delivery_attempt_count'   => $delivery->getAttemptCount(),
            'delivery_last_attempt_at' => self::optional($delivery->getLastAttemptAt()),
            'delivery_last_outcome_at' => self::optional($delivery->getLastOutcomeAt()),
            'delivery_last_failure'    => $delivery->getLastFailure()?->value
        ];
    }

    /**
     * Restores exact package-owned state without replaying provider effects
     *
     * @param array<string, mixed> $row
     */
    public static function hydrate(array $row): EmailChangeGrant
    {
        $userId = UserId::fromString((string) $row['user_id']);
        $delivery = PersistedEmailChangeDelivery::reconstitute(
            EmailChangeDeliveryId::fromString((string) $row['delivery_id']),
            $userId,
            EmailAddress::fromString((string) $row['delivery_email']),
            $row['delivery_ciphertext'] === null ? null : EncryptedCredentialMaterial::fromString(
                (string) $row['delivery_ciphertext']
            ),
            self::parse($row['delivery_expires_at']),
            self::parse($row['delivery_due_at']),
            CredentialDeliveryStatus::from((string) $row['delivery_status']),
            $row['delivery_claim_token'] === null ? null : CredentialDeliveryClaimToken::fromString(
                (string) $row['delivery_claim_token']
            ),
            self::parseOptional($row['delivery_claimed_at']),
            self::parseOptional($row['delivery_lease_until']),
            (int) $row['delivery_attempt_count'],
            self::parseOptional($row['delivery_last_attempt_at']),
            self::parseOptional($row['delivery_last_outcome_at']),
            $row['delivery_last_failure'] === null ? null : CredentialDeliveryFailure::from(
                (string) $row['delivery_last_failure']
            )
        );

        return PersistedEmailChangeGrant::reconstitute(
            EmailChangeGrantId::fromString((string) $row['id']),
            $userId,
            (string) $row['credential_digest'],
            self::parse($row['expires_at']),
            $delivery,
            self::parseOptional($row['consumed_at']),
            self::parseOptional($row['revoked_at']),
            self::parseOptional($row['expired_at']),
            (int) $row['revision']
        );
    }

    /**
     * Formats a date for PostgreSQL
     */
    private static function date(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }

    /**
     * Formats an optional date for PostgreSQL
     */
    private static function optional(?DateTimeImmutable $value): ?string
    {
        return $value === null ? null : self::date($value);
    }

    /**
     * Parses a required stored date
     */
    private static function parse(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable((string) $value);
    }

    /**
     * Parses an optional stored date
     */
    private static function parseOptional(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : self::parse($value);
    }
}
