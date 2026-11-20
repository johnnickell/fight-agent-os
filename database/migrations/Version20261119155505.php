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
        return 'Discards legacy email-change grants and requires recorded origin for new grants';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL required.');
        $this->addSql('LOCK TABLE email_change_grants IN ACCESS EXCLUSIVE MODE');
        // Add the columns first so an unexpected new-format schema fails before any deletion.
        $this->addSql(<<<'SQL'
ALTER TABLE email_change_grants
    ADD COLUMN requester_id UUID,
    ADD COLUMN old_mailbox VARCHAR(320)
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint
        WHERE contype = 'f' AND confrelid = 'email_change_grants'::regclass) THEN
        RAISE EXCEPTION 'Unexpected foreign key to email-change grants; refusing to discard legacy grants';
    END IF;
END $$
SQL);
        // The data owner approved discarding these grants, not changing User or reservation state.
        $this->addSql('DELETE FROM ONLY email_change_grants');
        $this->addSql(<<<'SQL'
ALTER TABLE email_change_grants
    ALTER COLUMN requester_id SET NOT NULL,
    ALTER COLUMN old_mailbox SET NOT NULL
SQL);
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Discarded legacy grants cannot be recovered and new grant origin must not be removed.'
        );
    }
}
