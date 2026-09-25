<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates authoritative permission, role, and role-permission membership storage';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration requires PostgreSQL.'
        );

        $this->addSql(<<<'SQL'
CREATE TABLE permissions (
    id UUID NOT NULL,
    name VARCHAR(128) NOT NULL,
    tier VARCHAR(32) DEFAULT NULL,
    managed BOOLEAN NOT NULL,
    created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    CONSTRAINT pk_permissions PRIMARY KEY (id),
    CONSTRAINT uq_permissions_name UNIQUE (name),
    CONSTRAINT ck_permissions_managed_tier CHECK (
        (managed = TRUE AND tier IN ('ADMIN_SAFE', 'SUPER_ADMIN_ONLY'))
        OR (managed = FALSE AND tier IS NULL)
    ),
    CONSTRAINT ck_permissions_timestamps CHECK (updated_at >= created_at)
)
SQL);
        $this->addSql('CREATE INDEX idx_permissions_managed_order ON permissions (managed, created_at, id)');

        $this->addSql(<<<'SQL'
CREATE TABLE roles (
    id UUID NOT NULL,
    name VARCHAR(128) NOT NULL,
    managed BOOLEAN NOT NULL,
    created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
    CONSTRAINT pk_roles PRIMARY KEY (id),
    CONSTRAINT uq_roles_name UNIQUE (name),
    CONSTRAINT ck_roles_timestamps CHECK (updated_at >= created_at)
)
SQL);
        $this->addSql('CREATE INDEX idx_roles_managed_order ON roles (managed, created_at, id)');

        $this->addSql(<<<'SQL'
CREATE TABLE role_permissions (
    role_id UUID NOT NULL,
    permission_id UUID NOT NULL,
    CONSTRAINT pk_role_permissions PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE RESTRICT
)
SQL);
        $this->addSql('CREATE INDEX idx_role_permissions_permission ON role_permissions (permission_id, role_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE role_permissions');
        $this->addSql('DROP TABLE roles');
        $this->addSql('DROP TABLE permissions');
    }
}
