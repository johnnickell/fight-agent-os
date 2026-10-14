<?php

declare(strict_types=1);

namespace App\Adapter\Persistence;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\ExpiredCredentialDelivery;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDeliveryId;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantId;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use InvalidArgumentException;

/**
 * Class ExpiredCredentialDeliveryRecords
 *
 * Selects bounded secret-free expiry references under the package repository contract
 */
final class ExpiredCredentialDeliveryRecords
{
    /**
     * Constructs ExpiredCredentialDeliveryRecords
     */
    private function __construct()
    {
    }

    /**
     * Retrieves eligible current generations before ordering and limiting
     *
     * @return list<ExpiredCredentialDelivery>
     */
    public static function find(Connection $connection, string $purpose, DateTimeImmutable $at, int $limit): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Expiry page size must be between 1 and 100.');
        }
        $table = match ($purpose) {
            'activation' => 'activation_grants',
            'password_reset' => 'password_reset_grants',
            'email_change' => 'email_change_grants',
            default => throw new InvalidArgumentException('Unsupported expiry purpose.')
        };
        $eligibility = 'g.expired_at IS NULL';
        if ($purpose !== 'email_change') {
            $eligibility = <<<'SQL'
g.delivery_ciphertext IS NOT NULL AND g.delivery_status IN ('pending', 'retry_pending', 'claimed')
SQL;
        }
        $rows = $connection->fetchAllAssociative(
            <<<SQL
SELECT g.id, g.delivery_id, g.user_id, g.expires_at, g.revision, g.delivery_status FROM {$table} g
WHERE g.consumed_at IS NULL AND g.revoked_at IS NULL AND {$eligibility}
    AND g.expires_at <= ? AND NOT EXISTS (SELECT 1 FROM {$table} newer
        WHERE newer.user_id = g.user_id AND newer.generation > g.generation)
ORDER BY g.expires_at, g.delivery_id LIMIT ?
SQL,
            [$at->format('Y-m-d H:i:s.uP'), $limit],
            [ParameterType::STRING, ParameterType::INTEGER]
        );

        return array_map(static function (array $row) use ($purpose): ExpiredCredentialDelivery {
            $deliveryId = match ($purpose) {
                'activation' => ActivationDeliveryId::fromString((string) $row['delivery_id']),
                'password_reset' => PasswordResetDeliveryId::fromString((string) $row['delivery_id']),
                'email_change' => EmailChangeDeliveryId::fromString((string) $row['delivery_id'])
            };

            return new ExpiredCredentialDelivery(
                $purpose,
                $deliveryId,
                UserId::fromString((string) $row['user_id']),
                $purpose === 'email_change' ? EmailChangeGrantId::fromString((string) $row['id']) : null,
                new DateTimeImmutable((string) $row['expires_at']),
                (int) $row['revision'],
                CredentialDeliveryStatus::from((string) $row['delivery_status'])
            );
        }, $rows);
    }
}
