<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20260926002702
 */
final class Version20260926002702 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Creates authoritative activation grant generations and recoverable encrypted delivery state';
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

        $this->addSql(<<<'SQL'
CREATE TABLE activation_grants (
    id UUID NOT NULL,
    user_id UUID NOT NULL,
    generation INTEGER NOT NULL,
    predecessor_id UUID DEFAULT NULL,
    credential_digest CHAR(64) NOT NULL,
    expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    consumed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    revoked_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    revision INTEGER NOT NULL,
    delivery_id UUID NOT NULL,
    delivery_email VARCHAR(320) NOT NULL,
    delivery_ciphertext TEXT DEFAULT NULL,
    delivery_due_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    delivery_status VARCHAR(32) NOT NULL,
    delivery_claim_token UUID DEFAULT NULL,
    delivery_claimed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    delivery_lease_until TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    delivery_attempt_count INTEGER NOT NULL,
    delivery_last_attempt_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    delivery_last_outcome_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    delivery_last_failure VARCHAR(32) DEFAULT NULL,
    CONSTRAINT pk_activation_grants PRIMARY KEY (id),
    CONSTRAINT fk_activation_grants_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uq_activation_grants_delivery_id UNIQUE (delivery_id),
    CONSTRAINT uq_activation_grants_identity_owner UNIQUE (id, user_id),
    CONSTRAINT uq_activation_grants_predecessor UNIQUE (predecessor_id),
    CONSTRAINT fk_activation_grants_predecessor FOREIGN KEY (predecessor_id, user_id)
        REFERENCES activation_grants (id, user_id) ON DELETE CASCADE,
    CONSTRAINT uq_activation_grants_generation UNIQUE (user_id, generation),
    CONSTRAINT uq_activation_grants_user_digest UNIQUE (user_id, credential_digest),
    CONSTRAINT ck_activation_grants_generation CHECK (
        revision >= 0 AND ((generation = 0 AND predecessor_id IS NULL)
            OR (generation > 0 AND predecessor_id IS NOT NULL))
    ),
    CONSTRAINT ck_activation_grants_digest CHECK (credential_digest ~ '^[0-9a-f]{64}$'),
    CONSTRAINT ck_activation_grants_terminal CHECK (consumed_at IS NULL OR revoked_at IS NULL),
    CONSTRAINT ck_activation_grants_delivery_status CHECK (delivery_status IN (
        'pending', 'claimed', 'retry_pending', 'delivered', 'permanent_failure', 'expired', 'invalidated'
    )),
    CONSTRAINT ck_activation_grants_delivery_claim CHECK (
        (delivery_status = 'claimed' AND delivery_claim_token IS NOT NULL
            AND delivery_claimed_at IS NOT NULL AND delivery_lease_until > delivery_claimed_at)
        OR (delivery_status <> 'claimed' AND delivery_claim_token IS NULL
            AND delivery_claimed_at IS NULL AND delivery_lease_until IS NULL)
    ),
    CONSTRAINT ck_activation_grants_delivery_material CHECK (
        (delivery_status IN ('pending', 'claimed', 'retry_pending')
            AND delivery_ciphertext IS NOT NULL AND length(delivery_ciphertext) > 0)
        OR (delivery_status IN ('delivered', 'permanent_failure', 'expired', 'invalidated')
            AND delivery_ciphertext IS NULL)
    ),
    CONSTRAINT ck_activation_grants_delivery_attempts CHECK (delivery_attempt_count >= 0)
)
SQL);
        $this->addSql('CREATE INDEX idx_activation_grants_due ON activation_grants (delivery_due_at, delivery_id)');
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activation_grants');
    }
}
