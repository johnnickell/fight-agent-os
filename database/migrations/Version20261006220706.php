<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261006220706
 */
final class Version20261006220706 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Adopts non-null permission tiers, exact email reservation bindings and bounded expiry discovery';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration requires PostgreSQL.'
        );
        $this->addSql('LOCK TABLE permissions, roles, role_permissions, email_change_grants IN ACCESS EXCLUSIVE MODE');
        // No historical email reservation binding can be inferred safely, including from a matching email string.
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM email_change_grants) THEN
        RAISE EXCEPTION 'Email grant history requires an explicit reconciliation decision before adoption';
    END IF;
    IF EXISTS (SELECT 1 FROM permissions WHERE
        (managed AND (tier IS NULL OR tier NOT IN ('ADMIN_SAFE', 'SUPER_ADMIN_ONLY')))
        OR (NOT managed AND tier IS NOT NULL)) THEN
        RAISE EXCEPTION 'Unsupported legacy permission tier state';
    END IF;
    IF EXISTS (SELECT 1 FROM roles WHERE name = 'ROLE_SUPER_ADMIN' AND NOT managed)
        OR EXISTS (SELECT 1 FROM role_permissions rp
            JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id
            WHERE p.tier = 'SUPER_ADMIN_ONLY' AND (NOT r.managed OR r.name <> 'ROLE_SUPER_ADMIN')) THEN
        RAISE EXCEPTION 'Protected role membership requires explicit reconciliation';
    END IF;
END $$
SQL);
        $this->addSql('ALTER TABLE permissions DROP CONSTRAINT ck_permissions_managed_tier');
        $this->addSql("UPDATE permissions SET tier = 'ADMIN_SAFE' WHERE NOT managed AND tier IS NULL");
        $this->addSql('ALTER TABLE permissions ALTER COLUMN tier SET NOT NULL');
        $this->addSql(<<<'SQL'
ALTER TABLE permissions ADD CONSTRAINT ck_permissions_managed_tier CHECK (
    tier IN ('ADMIN_SAFE', 'SUPER_ADMIN_ONLY') AND (managed OR tier = 'ADMIN_SAFE')
)
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE email_change_grants ADD COLUMN email_change_reservation_revision INTEGER NOT NULL
    CHECK (email_change_reservation_revision > 0)
SQL);
        $this->addSql(<<<'SQL'
CREATE INDEX idx_activation_grants_expired ON activation_grants (expires_at, delivery_id)
WHERE consumed_at IS NULL AND revoked_at IS NULL AND delivery_ciphertext IS NOT NULL
    AND delivery_status IN ('pending', 'retry_pending', 'claimed')
SQL);
        $this->addSql(<<<'SQL'
CREATE INDEX idx_password_reset_grants_expired ON password_reset_grants (expires_at, delivery_id)
WHERE consumed_at IS NULL AND revoked_at IS NULL AND delivery_ciphertext IS NOT NULL
    AND delivery_status IN ('pending', 'retry_pending', 'claimed')
SQL);
        $this->addSql(<<<'SQL'
CREATE INDEX idx_email_change_grants_expired ON email_change_grants (expires_at, delivery_id)
WHERE consumed_at IS NULL AND revoked_at IS NULL AND expired_at IS NULL
SQL);
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Reservation bindings must not be discarded by a downgrade.');
    }
}
