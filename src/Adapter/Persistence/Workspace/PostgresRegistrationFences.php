<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Workspace;

use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Application\Workspace\RegistrationFences;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;

/**
 * Class PostgresRegistrationFences
 *
 * Integrates the real authority writers before reading current authentication and operation permissions
 */
final readonly class PostgresRegistrationFences implements RegistrationFences
{
    /**
     * Constructs PostgresRegistrationFences
     */
    public function __construct(
        private Connection $connection,
        private AuthorizationReferenceFences $references,
        private AuthenticationAuthorityFences $authentication
    ) {
    }

    /**
     * @inheritDoc
     */
    public function holdAuthority(AuthenticationContext $authentication): void
    {
        $this->references->holdPermissionReferences();
        $this->references->holdRoleReferences();
        $this->authentication->hold($authentication->getUserId());
        // Assignment and authentication writers update this row; session writers update their own row.
        $this->connection->executeQuery('SELECT id FROM users WHERE id = ? FOR UPDATE', [
            $authentication->getUserId()->toString()
        ]);
        $this->connection->executeQuery('SELECT id FROM refresh_sessions WHERE id = ? FOR UPDATE', [
            $authentication->getRefreshSessionId()->toString()
        ]);
    }
}
