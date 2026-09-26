<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926002821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates purpose-separated password-reset grant and recoverable delivery generations';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration requires PostgreSQL.'
        );
        $this->addSql(<<<'SQL'
CREATE TABLE password_reset_grants (
    id UUID NOT NULL,
    user_id UUID NOT NULL,
    generation INTEGER NOT NULL,
    credential_digest CHAR(64) NOT NULL,
    expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    consumed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    revoked_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    revision INTEGER NOT NULL,
    delivery_id UUID NOT NULL,
    delivery_email VARCHAR(320) NOT NULL,
    delivery_ciphertext TEXT DEFAULT NULL,
    delivery_expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    delivery_due_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    delivery_status VARCHAR(32) NOT NULL,
    delivery_claim_token UUID DEFAULT NULL,
    delivery_claimed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    delivery_lease_until TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    delivery_attempt_count INTEGER NOT NULL,
    delivery_last_attempt_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    delivery_last_outcome_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    delivery_last_failure VARCHAR(32) DEFAULT NULL,
    CONSTRAINT pk_password_reset_grants PRIMARY KEY (id),
    CONSTRAINT uq_password_reset_grants_delivery UNIQUE (delivery_id),
    CONSTRAINT uq_password_reset_grants_generation UNIQUE (user_id, generation),
    CONSTRAINT uq_password_reset_grants_user_digest UNIQUE (user_id, credential_digest),
    CONSTRAINT fk_password_reset_grants_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT ck_password_reset_grants_generation CHECK (generation >= 0 AND revision >= 0),
    CONSTRAINT ck_password_reset_grants_digest CHECK (credential_digest ~ '^[0-9a-f]{64}$'),
    CONSTRAINT ck_password_reset_grants_authority CHECK (consumed_at IS NULL OR revoked_at IS NULL),
    CONSTRAINT ck_password_reset_grants_expiry CHECK (delivery_expires_at = expires_at AND delivery_due_at <= expires_at),
    CONSTRAINT ck_password_reset_grants_delivery_status CHECK (
        delivery_status IN ('pending', 'claimed', 'retry_pending', 'delivered', 'permanent_failure', 'expired', 'invalidated')
    ),
    CONSTRAINT ck_password_reset_grants_delivery_shape CHECK (
        delivery_attempt_count >= 0
        AND ((delivery_status = 'claimed' AND delivery_ciphertext IS NOT NULL
            AND delivery_claim_token IS NOT NULL AND delivery_claimed_at IS NOT NULL
            AND delivery_lease_until IS NOT NULL AND delivery_lease_until > delivery_claimed_at)
          OR (delivery_status <> 'claimed' AND delivery_claim_token IS NULL
            AND delivery_claimed_at IS NULL AND delivery_lease_until IS NULL))
        AND ((delivery_status IN ('pending', 'retry_pending') AND delivery_ciphertext IS NOT NULL)
          OR (delivery_status NOT IN ('pending', 'retry_pending')))
        AND ((delivery_status IN ('delivered', 'permanent_failure', 'expired', 'invalidated')
            AND delivery_ciphertext IS NULL)
          OR (delivery_status NOT IN ('delivered', 'permanent_failure', 'expired', 'invalidated')))
        AND (delivery_ciphertext IS NULL OR length(delivery_ciphertext) > 0)
    ),
    CONSTRAINT ck_password_reset_grants_terminal CHECK (
        (consumed_at IS NULL AND revoked_at IS NULL)
        OR delivery_ciphertext IS NULL
    )
)
SQL);
        $this->addSql('CREATE INDEX idx_password_reset_grants_due ON password_reset_grants (delivery_due_at, delivery_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_reset_grants');
    }
}
