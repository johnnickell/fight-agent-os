<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261119155505
 */
final class Version20261119155505 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Persists the original requester and old mailbox of each email-change generation';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL required.');
        $this->addSql('LOCK TABLE email_change_grants IN ACCESS EXCLUSIVE MODE');
        // The old schema cannot prove either part of the origin, even for terminal generations.
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM email_change_grants) THEN
        RAISE EXCEPTION 'Email-change history requires explicit origin reconciliation before adoption';
    END IF;
END $$
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE email_change_grants
    ADD COLUMN requester_id UUID NOT NULL,
    ADD COLUMN old_mailbox VARCHAR(320) NOT NULL
SQL);
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Email-change requester and old mailbox must not be discarded.');
    }
}
