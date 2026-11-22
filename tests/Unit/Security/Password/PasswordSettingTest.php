<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Password;

use App\Application\Security\Password\CompromisedPasswordChecker;
use App\Application\Security\Password\PasswordRejected;
use App\Application\Security\Password\PasswordSetting;
use App\Domain\Security\Password\PasswordRejection;
use App\Domain\Security\Password\PasswordStructure;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Proves shared password acceptance rejects unsafe candidates and fails closed
 */
final class PasswordSettingTest extends TestCase
{
    /**
     * Avoids corpus lookup for structurally invalid input
     */
    public function testStructuralRejection(): void
    {
        $checker = $this->createMock(CompromisedPasswordChecker::class);
        $checker->expects(self::never())->method('isCompromised');
        $setting = new PasswordSetting(new PasswordStructure(), $checker);
        try {
            $setting->requireAccepted('short');
            self::fail('Expected safe rejection.');
        } catch (PasswordRejected $failure) {
            self::assertSame(PasswordRejection::LENGTH, $failure->reason);
            self::assertNull($failure->getPrevious());
        }
    }

    /**
     * Rejects known-common candidates with a safe reason
     */
    public function testCommonRejection(): void
    {
        $checker = $this->createStub(CompromisedPasswordChecker::class);
        $checker->method('isCompromised')->willReturn(true);
        $setting = new PasswordSetting(new PasswordStructure(), $checker);
        try {
            $setting->requireAccepted('P@ssw0rd');
            self::fail('Expected common-password rejection.');
        } catch (PasswordRejected $failure) {
            self::assertSame(PasswordRejection::COMMON_PASSWORD, $failure->reason);
        }
    }

    /**
     * Checks accepted Unicode bytes without changing them
     */
    public function testAcceptedCandidate(): void
    {
        $candidate = " Distinct-Ωé42!e\u{0301} ";
        $checker = $this->createMock(CompromisedPasswordChecker::class);
        $checker->expects(self::once())->method('isCompromised')->with($candidate)->willReturn(false);
        (new PasswordSetting(new PasswordStructure(), $checker))->requireAccepted($candidate);
    }

    /**
     * Preserves checker unavailability without retaining its cause
     */
    public function testCheckerFailureIsClosed(): void
    {
        $candidate = 'Distinct-Fixture42!';
        $checker = $this->createStub(CompromisedPasswordChecker::class);
        $checker->method('isCompromised')->willThrowException(new RuntimeException($candidate));
        $setting = new PasswordSetting(new PasswordStructure(), $checker);
        try {
            $setting->requireAccepted($candidate);
            self::fail('Expected a safe infrastructure failure.');
        } catch (RuntimeException $failure) {
            self::assertSame('Password checking failed.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }
}
