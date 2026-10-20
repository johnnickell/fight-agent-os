<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261019204503
 *
 * Creates bounded activation admission storage
 */
final class Version20261019204503 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Add independently committed activation admission budgets';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE activation_rate_limits (
    key CHAR(64) PRIMARY KEY,
    window_end BIGINT NOT NULL,
    attempts INTEGER NOT NULL CHECK (attempts > 0)
)
SQL
        );
        $this->addSql('CREATE INDEX activation_rate_expiry ON activation_rate_limits (window_end)');
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activation_rate_limits');
    }
}
