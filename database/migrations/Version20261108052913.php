<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261108052913
 *
 * Preserves target identities, source claims, grants and historical attribution through the Project cutover
 */
final class Version20261108052913 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Cut registration over to Projects and detach historical attribution from ordinary response replay';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repository_registrations RENAME TO projects');
        $this->addSql('ALTER TABLE repository_sources RENAME TO project_sources');
        $this->addSql('ALTER TABLE project_sources RENAME COLUMN repository_id TO project_id');
        $this->addSql('ALTER TABLE workspace_audit RENAME COLUMN repository_id TO project_id');
        $this->addSql('ALTER TABLE workspace_operations RENAME TO workspace_history');
        $this->addSql('ALTER TABLE workspace_history RENAME COLUMN repository_id TO project_id');
        // Preserve independently useful initial metadata/source history, not an original HTTP response.
        // Attribution, event identities and audit correlation remain unchanged, even if current facts differ.
        $this->addSql(<<<'SQL'
ALTER TABLE workspace_history ADD COLUMN display_name VARCHAR(480),
    ADD COLUMN canonical_slug VARCHAR(80), ADD COLUMN sources JSONB
SQL);
        $this->addSql(<<<'SQL'
UPDATE workspace_history SET
    display_name = original_result->>'display_name',
    canonical_slug = original_result->>'slug',
    sources = jsonb_build_object('primary', original_result::jsonb->'primary_source',
        'equivalent', original_result::jsonb->'equivalent_sources')
WHERE command_kind = 'register_repository'
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE workspace_history DROP COLUMN idempotency_key, DROP COLUMN request_digest, DROP COLUMN original_result
SQL);
        $this->addSql('ALTER INDEX uq_workspace_repository_active_slug RENAME TO uq_workspace_project_active_slug');
        $this->addSql('ALTER INDEX uq_workspace_repository_active_source RENAME TO uq_workspace_project_active_source');
        $this->addSql('ALTER INDEX ix_repository_registration_page RENAME TO ix_project_page');
        $this->addSql('ALTER INDEX uq_repository_primary_source RENAME TO uq_project_primary_source');
        foreach (
            [
            'projects'          => [
                'repository_registrations_pkey'              => 'projects_pkey',
                'repository_registrations_workspace_id_fkey' => 'fk_project_workspace',
                'ck_repository_initial_lifecycle'            => 'ck_project_active_lifecycle',
                'ck_repository_positive_revisions'           => 'ck_project_positive_revisions',
                'ck_repository_slug'                         => 'ck_project_slug'
            ],
            'project_sources'   => [
                'repository_sources_pkey'                     => 'project_sources_pkey',
                'repository_sources_descriptor_version_check' => 'project_sources_descriptor_version_check',
                'repository_sources_source_key_check'         => 'project_sources_source_key_check',
                'repository_sources_source_role_check'        => 'project_sources_source_role_check'
            ],
            'workspace_history' => [
                'workspace_operations_pkey'               => 'workspace_history_pkey',
                'workspace_operations_actor_type_check'   => 'workspace_history_actor_type_check',
                'workspace_operations_command_kind_check' => 'workspace_history_command_kind_check',
                'workspace_operations_workspace_id_fkey'  => 'fk_workspace_history_workspace'
            ]
            ] as $table => $constraints
        ) {
            foreach ($constraints as $previous => $current) {
                $this->addSql('ALTER TABLE '.$table.' RENAME CONSTRAINT '.$previous.' TO '.$current);
            }
        }
        $this->addSql(<<<'SQL'
ALTER TABLE projects RENAME CONSTRAINT uq_repository_workspace_identity TO uq_project_workspace_identity
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE project_sources RENAME CONSTRAINT fk_repository_source_scope TO fk_project_source_scope
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE workspace_audit RENAME CONSTRAINT fk_workspace_audit_repository TO fk_workspace_audit_project
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE workspace_history RENAME CONSTRAINT fk_workspace_operation_repository TO fk_workspace_history_project
SQL);
        $this->addSql('ALTER TABLE workspace_audit DROP CONSTRAINT workspace_audit_action_check');
        $this->addSql(<<<'SQL'
ALTER TABLE workspace_audit ADD CONSTRAINT workspace_audit_action_check
    CHECK (action IN ('workspace_bootstrapped', 'repository_registered', 'project_registered'))
SQL);
        // Rename only the managed identities defined by the installation; preserve memberships and references.
        $this->addSql(<<<'SQL'
UPDATE permissions SET name = 'READ_PROJECTS'
WHERE id = '01a15945-5304-4535-b0df-f4f6f3b449eb' AND name = 'READ_REPOSITORY_REGISTRATIONS'
SQL);
        $this->addSql(<<<'SQL'
UPDATE permissions SET name = 'REGISTER_PROJECTS'
WHERE id = '01a15945-5304-4783-a78c-e0d647ec8235' AND name = 'REGISTER_REPOSITORIES'
SQL);
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Ordinary replay snapshots cannot be reconstructed from current Project facts.'
        );
    }
}
