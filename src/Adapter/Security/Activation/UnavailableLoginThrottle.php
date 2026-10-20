<?php

declare(strict_types=1);

namespace App\Adapter\Security\Activation;

use Fight\AccessControl\Application\AccessControl\User\Service\LoginThrottle;
use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Class UnavailableLoginThrottle
 *
 * Denies login until its separately owned persistent throttle is implemented
 */
final readonly class UnavailableLoginThrottle implements LoginThrottle
{
    /**
     * @inheritDoc
     */
    public function allows(EmailAddress $email): bool
    {
        return false;
    }
}
