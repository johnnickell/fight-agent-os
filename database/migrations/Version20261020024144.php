<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261020024144
 *
 * Stores bounded HMAC-keyed rolling authentication attempt histories
 */
final class Version20261020024144 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Store bounded rolling authentication admission attempts';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE auth_rate_limits (
    key CHAR(64) PRIMARY KEY CHECK (key ~ '^[0-9a-f]{64}$'),
    hits JSONB NOT NULL CHECK (jsonb_typeof(hits) = 'array' AND jsonb_array_length(hits) BETWEEN 1 AND 1000),
    expires_at BIGINT NOT NULL CHECK (expires_at > 0)
)
SQL);
        $this->addSql('CREATE INDEX auth_rate_expiry ON auth_rate_limits (expires_at)');
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE auth_rate_limits');
    }
}
