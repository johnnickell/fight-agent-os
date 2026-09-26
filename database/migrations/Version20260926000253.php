<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20260926000253
 */
final class Version20260926000253 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Enforces one digest namespace across current and spent refresh credentials';
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
CREATE TABLE refresh_session_credential_claims (
    credential_digest CHAR(64) NOT NULL,
    refresh_session_id UUID NOT NULL,
    CONSTRAINT pk_refresh_session_credential_claims PRIMARY KEY (credential_digest),
    CONSTRAINT uq_refresh_session_credential_claims_owner UNIQUE (refresh_session_id, credential_digest),
    CONSTRAINT fk_refresh_session_credential_claims_session
        FOREIGN KEY (refresh_session_id) REFERENCES refresh_sessions (id) ON DELETE CASCADE
)
SQL);
        $this->addSql(<<<'SQL'
INSERT INTO refresh_session_credential_claims (credential_digest, refresh_session_id)
SELECT credential_digest, id FROM refresh_sessions
UNION ALL
SELECT credential_digest, refresh_session_id FROM refresh_session_used_credentials
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE refresh_session_used_credentials
    ADD CONSTRAINT fk_refresh_session_used_credentials_claim
    FOREIGN KEY (refresh_session_id, credential_digest)
    REFERENCES refresh_session_credential_claims (refresh_session_id, credential_digest)
SQL);
        $this->addSql(<<<'SQL'
CREATE FUNCTION claim_refresh_session_credential() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'UPDATE' THEN
        IF NEW.credential_digest = OLD.credential_digest THEN
            RETURN NEW;
        END IF;
    END IF;
    INSERT INTO refresh_session_credential_claims (credential_digest, refresh_session_id)
    VALUES (NEW.credential_digest, NEW.id);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_refresh_sessions_claim_credential
AFTER INSERT OR UPDATE OF credential_digest ON refresh_sessions
FOR EACH ROW EXECUTE FUNCTION claim_refresh_session_credential()
SQL);
        $this->addSql(<<<'SQL'
CREATE FUNCTION reject_current_credential_history() RETURNS trigger AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM refresh_sessions
        WHERE id = NEW.refresh_session_id AND credential_digest = NEW.credential_digest
    ) THEN
        RAISE EXCEPTION 'Current refresh credential cannot be historical' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_refresh_session_used_credentials_not_current
BEFORE INSERT OR UPDATE ON refresh_session_used_credentials
FOR EACH ROW EXECUTE FUNCTION reject_current_credential_history()
SQL);
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP TRIGGER trg_refresh_session_used_credentials_not_current ON refresh_session_used_credentials'
        );
        $this->addSql('DROP FUNCTION reject_current_credential_history()');
        $this->addSql('DROP TRIGGER trg_refresh_sessions_claim_credential ON refresh_sessions');
        $this->addSql('DROP FUNCTION claim_refresh_session_credential()');
        $this->addSql(
            'ALTER TABLE refresh_session_used_credentials DROP CONSTRAINT fk_refresh_session_used_credentials_claim'
        );
        $this->addSql('DROP TABLE refresh_session_credential_claims');
    }
}
