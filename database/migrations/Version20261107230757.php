<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261107230757
 *
 * Separates required audit identity from replay bookkeeping while preserving all existing history
 */
final class Version20261107230757 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Detach Workspace audit from response snapshots without deleting identities or evidence';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workspace_audit DROP CONSTRAINT workspace_audit_operation_id_fkey');
        $this->addSql('ALTER TABLE workspace_audit RENAME COLUMN operation_id TO id');
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        // New required audit deliberately has no replay row. Never invent one or discard that history.
        $this->throwIrreversibleMigrationException(
            'Independent Workspace audit cannot be rebound to response snapshots.'
        );
    }
}
