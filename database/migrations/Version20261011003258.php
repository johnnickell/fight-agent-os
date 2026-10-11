<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261011003258
 */
final class Version20261011003258 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Persists Feature definitions and protected Permission references';
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
CREATE TABLE features (
    id UUID PRIMARY KEY,
    name VARCHAR(128) NOT NULL UNIQUE,
    permission_id UUID NOT NULL REFERENCES permissions(id) ON DELETE RESTRICT,
    status VARCHAR(7) NOT NULL CHECK (status IN ('off', 'preview', 'on')),
    revision INTEGER NOT NULL CHECK (revision > 0)
)
SQL);
        $this->addSql('CREATE INDEX idx_features_permission_id ON features (permission_id)');
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Feature bindings require explicit retirement before removal.');
    }
}
