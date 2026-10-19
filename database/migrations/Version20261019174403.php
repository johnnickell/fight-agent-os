<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261019174403
 *
 * Extends bounded invitation admission with operation and target scopes
 */
final class Version20261019174403 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Adds atomic actor and actor-target recovery rate budgets';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_rate_limits DROP CONSTRAINT invitation_rate_limits_operation_check');
        $this->addSql('ALTER TABLE invitation_rate_limits ALTER COLUMN operation TYPE VARCHAR(7)');
        $this->addSql(
            <<<'SQL'
ALTER TABLE invitation_rate_limits ADD CHECK (operation IN ('issue', 'status', 'retry', 'resend', 'correct'))
SQL
        );
        $this->addSql("ALTER TABLE invitation_rate_limits ADD COLUMN target_scope VARCHAR(36) NOT NULL DEFAULT ''");
        $this->addSql('ALTER TABLE invitation_rate_limits DROP CONSTRAINT invitation_rate_limits_pkey');
        $this->addSql('ALTER TABLE invitation_rate_limits ADD PRIMARY KEY (actor_id, operation, target_scope)');
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restoring the prior limiter would discard live recovery budgets.');
    }
}
