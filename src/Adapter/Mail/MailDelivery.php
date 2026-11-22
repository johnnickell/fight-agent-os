<?php

declare(strict_types=1);

namespace App\Adapter\Mail;

use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;

/**
 * Interface MailDelivery
 *
 * Delivers one invocation-lifetime message without retaining its private content
 */
interface MailDelivery
{
    /**
     * Attempts delivery with a stable identity and returns only a classified outcome
     */
    public function deliver(MailMessage $message): CredentialDeliveryOutcome;
}
