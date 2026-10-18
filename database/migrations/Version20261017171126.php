<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261017171126
 */
final class Version20261017171126 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Stores bounded atomic actor/action invitation admission windows without target or credential keys';
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
CREATE TABLE invitation_rate_limits (
    actor_id UUID NOT NULL,
    operation VARCHAR(6) NOT NULL CHECK (operation IN ('issue', 'status')),
    window_end BIGINT NOT NULL,
    attempts INTEGER NOT NULL CHECK (attempts BETWEEN 1 AND 10000),
    PRIMARY KEY (actor_id, operation)
)
SQL);
        $this->addSql('CREATE INDEX invitation_rate_limits_expiry ON invitation_rate_limits (window_end)');
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE invitation_rate_limits');
    }
}
