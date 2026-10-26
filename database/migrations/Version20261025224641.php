<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261025224641
 *
 * Creates non-authoritative session presentation with closed coarse labels and bounded retention
 */
final class Version20261025224641 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Stores original coarse session presentation without credentials or raw request metadata';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL required.');
        $this->addSql(<<<'SQL'
CREATE TABLE session_presentation (
    session_id UUID PRIMARY KEY REFERENCES refresh_sessions(id) ON DELETE CASCADE,
    browser_family VARCHAR(7) NOT NULL CHECK (browser_family IN ('Chrome', 'Edge', 'Firefox', 'Safari', 'Unknown')),
    device_class VARCHAR(7) NOT NULL CHECK (device_class IN ('desktop', 'mobile', 'tablet', 'unknown')),
    expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL
)
SQL);
        $this->addSql('CREATE INDEX idx_session_presentation_expiry ON session_presentation (expires_at, session_id)');
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE session_presentation');
    }
}
