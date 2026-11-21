<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Fight\AccessControl\Domain\AccessControl\User\UserId;
use InvalidArgumentException;

/**
 * Class TrustedOperator
 *
 * Carries admitted installation attribution, not an authenticated application principal
 */
final readonly class TrustedOperator
{
    /**
     * Constructs TrustedOperator
     */
    public function __construct(public UserId $operatorId, public string $auditActorId)
    {
        if (
            preg_match('/\A[a-z][a-z0-9_-]{0,31}:[0-9a-f-]{36}\z/D', $auditActorId) !== 1
            || !str_ends_with($auditActorId, ':'.$operatorId->toString())
        ) {
            throw new InvalidArgumentException('Invalid installation operator attribution.');
        }
    }
}
