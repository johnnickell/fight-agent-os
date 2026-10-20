<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261020122131
 *
 * Introduces Workspace-owned registration, claims and atomic original-result evidence
 */
final class Version20261020122131 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Add explicit initial Workspace and Repository registration without physical authority';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE workspaces (
    id UUID PRIMARY KEY,
    installation_slot TEXT NOT NULL CONSTRAINT uq_workspaces_installation_slot UNIQUE,
    created_at TIMESTAMPTZ(6) NOT NULL,
    CONSTRAINT ck_workspaces_initial_slot CHECK (installation_slot = 'initial')
)
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE repository_registrations (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id),
    display_name VARCHAR(480) NOT NULL,
    canonical_slug VARCHAR(80) COLLATE "C" NOT NULL,
    lifecycle VARCHAR(16) NOT NULL,
    metadata_revision BIGINT NOT NULL,
    source_revision BIGINT NOT NULL,
    lifecycle_revision BIGINT NOT NULL,
    created_by UUID NOT NULL,
    updated_by UUID NOT NULL,
    created_at TIMESTAMPTZ(6) NOT NULL,
    updated_at TIMESTAMPTZ(6) NOT NULL,
    CONSTRAINT uq_repository_workspace_identity UNIQUE (workspace_id, id),
    CONSTRAINT ck_repository_initial_lifecycle CHECK (lifecycle = 'active'),
    CONSTRAINT ck_repository_positive_revisions
        CHECK (metadata_revision > 0 AND source_revision > 0 AND lifecycle_revision > 0),
    CONSTRAINT ck_repository_slug CHECK (canonical_slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')
)
SQL);
        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uq_workspace_repository_active_slug
ON repository_registrations (workspace_id, canonical_slug) WHERE lifecycle = 'active'
SQL);
        $this->addSql(<<<'SQL'
CREATE INDEX ix_repository_registration_page ON repository_registrations (workspace_id, created_at, id)
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE repository_sources (
    workspace_id UUID NOT NULL,
    repository_id UUID NOT NULL,
    descriptor_version INTEGER NOT NULL CHECK (descriptor_version = 1),
    source_key TEXT COLLATE "C" NOT NULL CHECK (octet_length(source_key) <= 2048),
    source_role VARCHAR(16) NOT NULL CHECK (source_role IN ('primary', 'equivalent')),
    active BOOLEAN NOT NULL,
    PRIMARY KEY (repository_id, descriptor_version, source_key),
    CONSTRAINT fk_repository_source_scope FOREIGN KEY (workspace_id, repository_id)
        REFERENCES repository_registrations(workspace_id, id)
)
SQL);
        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uq_workspace_repository_active_source
ON repository_sources (workspace_id, descriptor_version, source_key) WHERE active
SQL);
        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uq_repository_primary_source ON repository_sources (repository_id) WHERE source_role = 'primary'
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE workspace_operations (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id),
    repository_id UUID,
    actor_type VARCHAR(32) NOT NULL CHECK (actor_type IN ('user', 'installation_operator')),
    actor_id UUID NOT NULL,
    command_kind VARCHAR(32) NOT NULL CHECK (command_kind IN ('bootstrap_workspace', 'register_repository')),
    idempotency_key VARCHAR(128) COLLATE "C" NOT NULL,
    request_digest CHAR(64) NOT NULL,
    original_result JSON NOT NULL,
    event_name VARCHAR(64),
    created_at TIMESTAMPTZ(6) NOT NULL,
    CONSTRAINT uq_workspace_operation_intent UNIQUE (workspace_id, actor_type, actor_id, command_kind, idempotency_key),
    CONSTRAINT fk_workspace_operation_repository FOREIGN KEY (workspace_id, repository_id)
        REFERENCES repository_registrations(workspace_id, id)
)
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE workspace_audit (
    operation_id UUID PRIMARY KEY REFERENCES workspace_operations(id),
    workspace_id UUID NOT NULL REFERENCES workspaces(id),
    repository_id UUID,
    actor_type VARCHAR(32) NOT NULL CHECK (actor_type IN ('user', 'installation_operator')),
    actor_id UUID NOT NULL,
    action VARCHAR(32) NOT NULL CHECK (action IN ('workspace_bootstrapped', 'repository_registered')),
    predecessor_revision BIGINT NOT NULL CHECK (predecessor_revision = 0),
    result_revision BIGINT NOT NULL CHECK (result_revision = 1),
    result_code VARCHAR(16) NOT NULL CHECK (result_code = 'created'),
    created_at TIMESTAMPTZ(6) NOT NULL,
    CONSTRAINT fk_workspace_audit_repository FOREIGN KEY (workspace_id, repository_id)
        REFERENCES repository_registrations(workspace_id, id)
)
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE reference_generation_limits (
    actor_id UUID PRIMARY KEY,
    window_start BIGINT NOT NULL,
    attempts INTEGER NOT NULL CHECK (attempts BETWEEN 1 AND 10)
)
SQL);
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DROP TABLE reference_generation_limits, workspace_audit, workspace_operations,
    repository_sources, repository_registrations, workspaces
SQL);
    }
}
