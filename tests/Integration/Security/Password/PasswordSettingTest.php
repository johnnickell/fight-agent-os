<?php

declare(strict_types=1);

namespace Tests\Integration\Security\Password;

use App\Adapter\Security\Password\Argon2idPasswords;
use App\Adapter\Security\Password\LocalCompromisedPasswords;
use App\Application\Security\Password\CompromisedPasswordChecker;
use App\Application\Security\Password\PasswordRejected;
use App\Application\Security\Password\PasswordSetting;
use App\Domain\Security\Password\PasswordRejection;
use App\Domain\Security\Password\PasswordStructure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * Proves the reusable setting contract with real corpus and password hashing
 */
final class PasswordSettingTest extends TestCase
{
    /**
     * Rejects a structurally valid common password and hashes accepted Unicode bytes unchanged
     */
    public function test_real_password_setting(): void
    {
        $passwords = new Argon2idPasswords();
        $setting = new PasswordSetting(
            new PasswordStructure(),
            new LocalCompromisedPasswords(dirname(__DIR__, 4).'/resources/passwords/common.sha256'),
            $passwords
        );
        self::assertSame(PasswordRejection::COMMON_PASSWORD, $setting->rejection('P@ssw0rd'));
        try {
            $setting->hash('P@ssw0rd');
            self::fail('Expected common-password rejection.');
        } catch (PasswordRejected $failure) {
            self::assertSame(PasswordRejection::COMMON_PASSWORD, $failure->reason);
        }
        $candidate = " Distinct-Ωé42!e\u{0301} ";
        self::assertNull($setting->rejection($candidate));
        $hash = $setting->hash($candidate);
        self::assertTrue($passwords->validate($candidate, $hash));
        self::assertFalse($passwords->validate(trim($candidate), $hash));
        self::assertFalse($passwords->needsRehash($hash));
        ob_start();
        var_dump($setting);
        $diagnostics = (string) ob_get_clean().print_r($setting, true).var_export($setting, true);
        foreach ([$candidate, $hash, hash('sha256', strtolower($candidate))] as $marker) {
            self::assertStringNotContainsString($marker, $diagnostics);
        }
    }

    /**
     * Redacts downstream secrets and arguments even when exception argument capture is enabled
     */
    public function test_secret_safe_failures_and_diagnostics(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        $candidate = 'Distinct-PrivateMarker42!';
        $hash = (new Argon2idPasswords())->hash($candidate);
        $lookup = hash('sha256', strtolower($candidate));
        $checker = new class ($candidate.$hash.$lookup) implements CompromisedPasswordChecker {
            /**
             * Constructs the faulting capability
             */
            public function __construct(private readonly string $privateMaterial)
            {
            }

            /**
             * @inheritDoc
             */
            public function isCompromised(#[SensitiveParameter] string $candidate): bool
            {
                throw new RuntimeException($this->privateMaterial);
            }
        };
        $setting = new PasswordSetting(new PasswordStructure(), $checker, new Argon2idPasswords());
        ob_start();
        try {
            $setting->hash($candidate);
            self::fail('Expected safe infrastructure failure.');
        } catch (Throwable $failure) {
            $output = (string) ob_get_clean();
            self::assertSame('', $output);
            self::assertSame('Password checking failed.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            $diagnostics = (string) $failure.print_r($failure, true).var_export($failure, true);
            foreach ([$candidate, $hash, $lookup] as $marker) {
                self::assertStringNotContainsString($marker, $diagnostics);
            }
            self::assertSame('"common_password"', json_encode(PasswordRejection::COMMON_PASSWORD));
        } finally {
            if (ob_get_level() > 0 && isset($output) === false) {
                ob_end_clean();
            }
            ini_set('zend.exception_ignore_args', $previous === false ? '1' : $previous);
        }
    }
}
