<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261011021031
 */
final class Version20261011021031 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Reserves Agent authority identities and direct Permission references without enabling Agent writers';
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
CREATE TABLE agents (
    id UUID PRIMARY KEY,
    name TEXT NOT NULL CHECK (length(name) > 0),
    state VARCHAR(11) NOT NULL CHECK (state IN ('provisioned', 'active', 'revoked')),
    credential_id UUID NOT NULL UNIQUE,
    credential_revision INTEGER NOT NULL CHECK (credential_revision >= 0),
    encrypted_hmac_shared_secret_envelope TEXT NOT NULL CHECK (length(encrypted_hmac_shared_secret_envelope) > 0),
    permission_assignment_revision INTEGER NOT NULL CHECK (permission_assignment_revision > 0),
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL CHECK (updated_at >= created_at)
)
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE agent_permission_assignments (
    agent_id UUID NOT NULL REFERENCES agents(id) ON DELETE RESTRICT,
    permission_id UUID NOT NULL REFERENCES permissions(id) ON DELETE RESTRICT,
    PRIMARY KEY (agent_id, permission_id)
)
SQL);
        $this->addSql(
            'CREATE INDEX idx_agent_permission_assignments_permission ON agent_permission_assignments (permission_id)'
        );
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Agent authority history requires explicit retirement before removal.'
        );
    }
}
