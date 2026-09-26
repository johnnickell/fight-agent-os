<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20260926011841
 */
final class Version20260926011841 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Creates append-only typed audit evidence and email-change credential generations';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL required.');
        $this->addSql(<<<'SQL'
CREATE TABLE audit_evidence (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    actor_id VARCHAR(128) NOT NULL,
    action VARCHAR(128) NOT NULL,
    subject_type VARCHAR(16) NOT NULL,
    subject_id UUID NOT NULL,
    context JSONB NOT NULL,
    recorded_at TIMESTAMP(6) WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ck_audit_evidence_actor CHECK (length(actor_id) > 0),
    CONSTRAINT ck_audit_evidence_action CHECK (action ~ '^[a-z][a-z0-9_.]*$'),
    CONSTRAINT ck_audit_evidence_subject CHECK (subject_type IN ('user', 'agent')),
    CONSTRAINT ck_audit_evidence_context CHECK (
        jsonb_typeof(context) = 'object' AND octet_length(context::text) <= 4096
    )
)
SQL);
        $this->addSql('CREATE INDEX idx_audit_evidence_subject ON audit_evidence (subject_type, subject_id, id)');
        $this->addSql(<<<'SQL'
CREATE TABLE email_change_grants (
    id UUID NOT NULL,
    user_id UUID NOT NULL,
    generation INTEGER NOT NULL,
    credential_digest CHAR(64) NOT NULL,
    expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    consumed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    revoked_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    expired_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
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
    CONSTRAINT pk_email_change_grants PRIMARY KEY (id),
    CONSTRAINT uq_email_change_grants_delivery UNIQUE (delivery_id),
    CONSTRAINT uq_email_change_grants_generation UNIQUE (user_id, generation),
    CONSTRAINT uq_email_change_grants_user_digest UNIQUE (user_id, credential_digest),
    CONSTRAINT fk_email_change_grants_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT ck_email_change_grants_generation CHECK (generation >= 0 AND revision >= 0),
    CONSTRAINT ck_email_change_grants_digest CHECK (credential_digest ~ '^[0-9a-f]{64}$'),
    CONSTRAINT ck_email_change_grants_authority CHECK (
        (consumed_at IS NOT NULL)::integer + (revoked_at IS NOT NULL)::integer + (expired_at IS NOT NULL)::integer <= 1
    ),
    CONSTRAINT ck_email_change_grants_expiry CHECK (delivery_expires_at = expires_at AND delivery_due_at <= expires_at),
    CONSTRAINT ck_email_change_grants_delivery_status CHECK (
        delivery_status IN (
            'pending', 'claimed', 'retry_pending', 'delivered', 'permanent_failure', 'expired', 'invalidated'
        )
    ),
    CONSTRAINT ck_email_change_grants_delivery_shape CHECK (
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
    CONSTRAINT ck_email_change_grants_terminal CHECK (
        (consumed_at IS NULL AND revoked_at IS NULL AND expired_at IS NULL) OR delivery_ciphertext IS NULL
    )
)
SQL);
        $this->addSql('CREATE INDEX idx_email_change_grants_due ON email_change_grants (delivery_due_at, delivery_id)');
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_change_grants');
        $this->addSql('DROP TABLE audit_evidence');
    }
}
