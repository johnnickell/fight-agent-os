<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Password;

use App\Application\Security\Password\CompromisedPasswordChecker;
use App\Application\Security\Password\PasswordRejected;
use App\Application\Security\Password\PasswordSetting;
use App\Domain\Security\Password\PasswordRejection;
use App\Domain\Security\Password\PasswordStructure;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Proves shared password setting rejects before hashing and fails closed
 */
final class PasswordSettingTest extends TestCase
{
    /**
     * Avoids corpus lookup and hashing for structurally invalid input
     */
    public function test_structural_rejection(): void
    {
        $checker = $this->createMock(CompromisedPasswordChecker::class);
        $checker->expects(self::never())->method('isCompromised');
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::never())->method('hash');
        $setting = new PasswordSetting(new PasswordStructure(), $checker, $hasher);
        self::assertSame(PasswordRejection::LENGTH, $setting->rejection('short'));
        try {
            $setting->hash('short');
            self::fail('Expected safe rejection.');
        } catch (PasswordRejected $failure) {
            self::assertSame(PasswordRejection::LENGTH, $failure->reason);
            self::assertSame('Password rejected: length', $failure->getMessage());
        }
    }

    /**
     * Rejects known-common candidates without hashing
     */
    public function test_common_rejection(): void
    {
        $checker = $this->createMock(CompromisedPasswordChecker::class);
        $checker->method('isCompromised')->willReturn(true);
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::never())->method('hash');
        $setting = new PasswordSetting(new PasswordStructure(), $checker, $hasher);
        self::assertSame(PasswordRejection::COMMON_PASSWORD, $setting->rejection('P@ssw0rd'));
        $this->expectException(PasswordRejected::class);
        $this->expectExceptionMessage('Password rejected: common_password');
        $setting->hash('P@ssw0rd');
    }

    /**
     * Passes accepted bytes unchanged to the injected hashing capability
     */
    public function test_accepted_hash(): void
    {
        $candidate = " Distinct-Ωé42!e\u{0301} ";
        $checker = $this->createMock(CompromisedPasswordChecker::class);
        $checker->expects(self::exactly(2))->method('isCompromised')->with($candidate)->willReturn(false);
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::once())->method('hash')->with($candidate)->willReturn('opaque-persistence-material');
        $setting = new PasswordSetting(new PasswordStructure(), $checker, $hasher);
        self::assertNull($setting->rejection($candidate));
        self::assertSame('opaque-persistence-material', $setting->hash($candidate));
    }

    /**
     * Propagates checker unavailability without insecure acceptance or hashing
     */
    public function test_checker_failure_is_closed(): void
    {
        $checker = $this->createMock(CompromisedPasswordChecker::class);
        $checker->method('isCompromised')->willThrowException(new RuntimeException('Password corpus unavailable.'));
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::never())->method('hash');
        $setting = new PasswordSetting(new PasswordStructure(), $checker, $hasher);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Password checking failed.');
        $setting->hash('Distinct-Ωé42!');
    }

    /**
     * Removes secret-bearing downstream exceptions rather than retaining a cause
     */
    public function test_hasher_failure_is_safe(): void
    {
        $candidate = 'Distinct-Sensitive42!';
        $checker = $this->createMock(CompromisedPasswordChecker::class);
        $checker->method('isCompromised')->willReturn(false);
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->method('hash')->willThrowException(new RuntimeException($candidate));
        $setting = new PasswordSetting(new PasswordStructure(), $checker, $hasher);
        try {
            $setting->hash($candidate);
            self::fail('Expected hashing failure.');
        } catch (RuntimeException $failure) {
            self::assertSame('Password hashing failed.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }
}
