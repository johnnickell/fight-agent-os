<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261005060145
 *
 * Indexes the package pending, retry and expired-lease discovery order
 */
final class Version20261005060145 extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Index invitation and password-reset recovery ordering including expired claims';
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
        foreach (['activation_grants', 'password_reset_grants'] as $table) {
            $this->addSql(sprintf(
                <<<'SQL'
CREATE INDEX idx_%1$s_recovery_order ON %1$s
((CASE WHEN delivery_status = 'claimed' THEN delivery_lease_until ELSE delivery_due_at END), delivery_id)
WHERE delivery_ciphertext IS NOT NULL
SQL,
                $table
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_password_reset_grants_recovery_order');
        $this->addSql('DROP INDEX idx_activation_grants_recovery_order');
    }
}
