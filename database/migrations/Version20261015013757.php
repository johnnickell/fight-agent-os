<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261015013757
 */
final class Version20261015013757 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Records an immutable installation-wide one-time bootstrap claim independently of identity lifecycle';
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
        // Deliberately no user foreign key: the claim commits before invitation and survives all lifecycle changes.
        $this->addSql(<<<'SQL'
CREATE TABLE installation_bootstrap (
    singleton BOOLEAN PRIMARY KEY DEFAULT TRUE CHECK (singleton),
    operator_id UUID NOT NULL,
    target_id UUID NOT NULL UNIQUE CHECK (target_id <> operator_id),
    email VARCHAR(254) NOT NULL CHECK (email = lower(email)),
    claimed_at TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp()
)
SQL);
        $this->addSql(<<<'SQL'
CREATE FUNCTION prevent_bootstrap_claim_change() RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Bootstrap claims are immutable';
END
$$
SQL);
        $this->addSql(<<<'SQL'
CREATE TRIGGER immutable_bootstrap_claim BEFORE UPDATE OR DELETE ON installation_bootstrap
FOR EACH ROW EXECUTE FUNCTION prevent_bootstrap_claim_change()
SQL);
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Bootstrap claims must never be reopened by rollback.');
    }
}
