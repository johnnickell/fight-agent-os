<?php

declare(strict_types=1);

namespace App\Adapter\Console;

use App\Domain\Authorization\TrustedOperator;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;

/**
 * Class TrustedOperatorInput
 *
 * Parses bounded selectors and formats the local operator's declared attribution
 */
final class TrustedOperatorInput
{
    /**
     * Requires a canonical UUID selector
     */
    public static function uuid(string $value): string
    {
        if (preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid operator input.');
        }

        return $value;
    }

    /**
     * Maps the declared local operator to typed attribution
     */
    public static function operator(string $value, string $prefix): TrustedOperator
    {
        $operatorId = UserId::fromString(self::uuid($value));

        return new TrustedOperator($operatorId, $prefix.':'.$operatorId->toString());
    }

    /**
     * Parses and normalizes the local invitation email
     */
    public static function email(string $value): EmailAddress
    {
        if (strlen($value) > 254 || preg_match('/[\x00-\x20\x7f-\xff]/', $value) === 1) {
            throw new InvalidArgumentException('Invalid invitation email.');
        }

        return EmailAddress::fromString(strtolower($value));
    }
}
