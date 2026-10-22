<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261021182244
 */
final class Version20261021182244 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Adopts persisted Role revisions, activation origins and immutable reset recovery authority';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL required.');
        $this->addSql('LOCK TABLE roles, activation_grants, password_reset_grants IN ACCESS EXCLUSIVE MODE');
        // Historical issuing authority cannot be inferred from a current User, including for terminal generations.
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM password_reset_grants) THEN
        RAISE EXCEPTION 'Reset history requires explicit issuing-authority reconciliation before adoption';
    END IF;
END $$
SQL);
        // This consumer never enabled registration before adoption: all existing activation issuance was private.
        $this->addSql("ALTER TABLE activation_grants ADD COLUMN origin VARCHAR(32)");
        $this->addSql("UPDATE activation_grants SET origin = 'private_invitation'");
        $this->addSql('ALTER TABLE activation_grants ALTER COLUMN origin SET NOT NULL');
        $this->addSql(<<<'SQL'
ALTER TABLE activation_grants ADD CONSTRAINT ck_activation_origin
    CHECK (origin IN ('private_invitation', 'registration'))
SQL);
        // Bootstrap one persisted incarnation per retained identity exactly once, never during hydration.
        $this->addSql('ALTER TABLE roles ADD COLUMN revision VARCHAR(46)');
        $this->addSql("UPDATE roles SET revision = gen_random_uuid()::text || ':1'");
        $this->addSql('ALTER TABLE roles ALTER COLUMN revision SET NOT NULL');
        $this->addSql(<<<'SQL'
ALTER TABLE roles ADD CONSTRAINT ck_roles_revision CHECK (
    revision ~ '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}:[1-9][0-9]{0,8}$'
)
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE role_incarnations (
    incarnation UUID PRIMARY KEY,
    role_id UUID NOT NULL
)
SQL);
        $this->addSql(<<<'SQL'
INSERT INTO role_incarnations (incarnation, role_id)
    SELECT split_part(revision, ':', 1)::uuid, id FROM roles
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE password_reset_grants
    ADD COLUMN recovery_authentication_version INTEGER NOT NULL CHECK (recovery_authentication_version > 0),
    ADD COLUMN recovery_canonical_email_revision INTEGER NOT NULL CHECK (recovery_canonical_email_revision >= 0)
SQL);
        $this->addSql('ALTER TABLE audit_evidence DROP CONSTRAINT ck_audit_evidence_subject');
        $this->addSql(<<<'SQL'
ALTER TABLE audit_evidence ADD CONSTRAINT ck_audit_evidence_subject CHECK (subject_type IN ('user', 'agent', 'role'))
SQL);
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Persisted authority and Role incarnations must not be discarded.');
    }
}
