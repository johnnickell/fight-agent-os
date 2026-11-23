<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Application\Authorization\UnavailableEmailChangeAdministration;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\Exception\EmailChangeAdministrationAuthorizationException;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Protects the default denial for unsupported cross-user administration
 */
final class UnavailableEmailChangeAdministrationTest extends TestCase
{
    /**
     * Denies a request to manage a different user's email change
     */
    public function testDeniesCrossUserAdministration(): void
    {
        $this->expectException(EmailChangeAdministrationAuthorizationException::class);
        $this->expectExceptionMessage('Email-change administration is unavailable.');

        $administration = new UnavailableEmailChangeAdministration();
        $administration->assertCanManageEmailChange(UserId::generate(), UserId::generate());
    }
}
