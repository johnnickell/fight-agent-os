<?php

declare(strict_types=1);

namespace App\Domain\Security\Password;

use SensitiveParameter;

/**
 * Class PasswordStructure
 *
 * Enforces password composition over unmodified Unicode code points
 */
final class PasswordStructure
{
    public const int MIN_LENGTH = 8;
    public const int MAX_LENGTH = 128;
    public const string COMPOSITION_GUIDANCE = 'Use 8–128 characters: uppercase, lowercase, number and symbol.';
    public const string GUIDANCE = self::COMPOSITION_GUIDANCE.' Known-common passwords cannot be used.';

    /**
     * Returns the first structural rejection without retaining the candidate
     */
    public function rejection(#[SensitiveParameter] string $candidate): ?PasswordRejection
    {
        if (preg_match('//u', $candidate) !== 1) {
            return PasswordRejection::INVALID_UTF8;
        }

        $length = preg_match_all('/./us', $candidate);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return PasswordRejection::LENGTH;
        }

        $categories = [
            '/\p{Lu}/u'       => PasswordRejection::UPPERCASE_REQUIRED,
            '/\p{Ll}/u'       => PasswordRejection::LOWERCASE_REQUIRED,
            '/\p{N}/u'        => PasswordRejection::NUMBER_REQUIRED,
            '/[\p{P}\p{S}]/u' => PasswordRejection::SYMBOL_REQUIRED
        ];
        foreach ($categories as $pattern => $reason) {
            if (preg_match($pattern, $candidate) !== 1) {
                return $reason;
            }
        }

        return null;
    }
}
