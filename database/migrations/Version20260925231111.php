<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20260925231111
 */
final class Version20260925231111 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Creates authoritative user identity, email-claim, role-assignment, and refresh-session storage';
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
CREATE TABLE users (
    id UUID NOT NULL,
    email VARCHAR(320) NOT NULL,
    state VARCHAR(32) NOT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    authentication_version INTEGER NOT NULL,
    authentication_authority_revision INTEGER NOT NULL,
    authorization_assignment_revision INTEGER NOT NULL,
    pending_email_change VARCHAR(320) DEFAULT NULL,
    email_change_reservation_revision INTEGER NOT NULL,
    canonical_email_revision INTEGER NOT NULL,
    created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    CONSTRAINT pk_users PRIMARY KEY (id),
    CONSTRAINT ck_users_state CHECK (
        state IN ('pending_activation', 'active', 'disabled', 'deleted')
    ),
    CONSTRAINT ck_users_password_shape CHECK (
        (state = 'pending_activation' AND password_hash IS NULL)
        OR (state <> 'pending_activation' AND password_hash IS NOT NULL)
    ),
    CONSTRAINT ck_users_revisions CHECK (
        authentication_version >= 1
        AND authentication_authority_revision >= 0
        AND authorization_assignment_revision >= 0
        AND email_change_reservation_revision >= 0
        AND canonical_email_revision >= 0
    ),
    CONSTRAINT ck_users_timestamps CHECK (updated_at >= created_at)
)
SQL);
        $this->addSql('CREATE INDEX idx_users_order ON users (created_at, id)');

        $this->addSql(<<<'SQL'
CREATE TABLE user_email_claims (
    user_id UUID NOT NULL,
    claim_type VARCHAR(32) NOT NULL,
    email VARCHAR(320) NOT NULL,
    CONSTRAINT pk_user_email_claims PRIMARY KEY (user_id, claim_type),
    CONSTRAINT uq_user_email_claims_email UNIQUE (email),
    CONSTRAINT ck_user_email_claims_type CHECK (claim_type IN ('canonical', 'reservation')),
    CONSTRAINT fk_user_email_claims_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE user_role_assignments (
    user_id UUID NOT NULL,
    role_id UUID NOT NULL,
    CONSTRAINT pk_user_role_assignments PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_role_assignments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_role_assignments_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
)
SQL);
        $this->addSql('CREATE INDEX idx_user_role_assignments_role ON user_role_assignments (role_id, user_id)');

        $this->addSql(<<<'SQL'
CREATE TABLE refresh_sessions (
    id UUID NOT NULL,
    user_id UUID NOT NULL,
    credential_digest CHAR(64) NOT NULL,
    created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    last_activity_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    rotated_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
    idle_expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    absolute_expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    authentication_version INTEGER NOT NULL,
    remembered BOOLEAN NOT NULL,
    revision INTEGER NOT NULL,
    revoked BOOLEAN NOT NULL,
    CONSTRAINT pk_refresh_sessions PRIMARY KEY (id),
    CONSTRAINT uq_refresh_sessions_credential_digest UNIQUE (credential_digest),
    CONSTRAINT fk_refresh_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT ck_refresh_sessions_revision CHECK (revision >= 0),
    CONSTRAINT ck_refresh_sessions_lifetime CHECK (
        idle_expires_at > created_at
        AND absolute_expires_at > created_at
        AND idle_expires_at <= absolute_expires_at
    ),
    CONSTRAINT ck_refresh_sessions_activity CHECK (last_activity_at >= created_at)
)
SQL);
        $this->addSql('CREATE INDEX idx_refresh_sessions_user_order ON refresh_sessions (user_id, created_at, id)');

        $this->addSql(<<<'SQL'
CREATE TABLE refresh_session_used_credentials (
    refresh_session_id UUID NOT NULL,
    sequence INTEGER NOT NULL,
    credential_digest CHAR(64) NOT NULL,
    CONSTRAINT pk_refresh_session_used_credentials PRIMARY KEY (refresh_session_id, sequence),
    CONSTRAINT uq_refresh_session_used_credentials_digest UNIQUE (credential_digest),
    CONSTRAINT ck_refresh_session_used_credentials_sequence CHECK (sequence >= 0),
    CONSTRAINT fk_refresh_session_used_credentials_session
        FOREIGN KEY (refresh_session_id) REFERENCES refresh_sessions (id) ON DELETE CASCADE
)
SQL);
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_session_used_credentials');
        $this->addSql('DROP TABLE refresh_sessions');
        $this->addSql('DROP TABLE user_role_assignments');
        $this->addSql('DROP TABLE user_email_claims');
        $this->addSql('DROP TABLE users');
    }
}
