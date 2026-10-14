<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use Fight\AccessControl\Application\AccessControl\EmailChangeGrant\Service\EmailChangeAdministrationAuthorization;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\Exception\EmailChangeAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class UnavailableEmailChangeAdministration
 *
 * Denies cross-user email-change administration until an installation permission is approved
 */
final readonly class UnavailableEmailChangeAdministration implements EmailChangeAdministrationAuthorization
{
    /**
     * @inheritDoc
     */
    public function assertCanManageEmailChange(UserId $actorId, UserId $userId): void
    {
        throw new EmailChangeAdministrationAuthorizationException('Email-change administration is unavailable.');
    }
}
