<?php

declare(strict_types=1);

namespace App\Application\Mail;

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
    public function deliver(MailMessage $message): MailOutcome;
}
