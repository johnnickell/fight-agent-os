<?php

declare(strict_types=1);

namespace App\Application\Mail;

/**
 * Enum MailOutcome
 *
 * Classifies a transport attempt without provider diagnostics or retry policy
 */
enum MailOutcome
{
    case DELIVERED;
    case RETRYABLE_FAILURE;
    case PERMANENT_FAILURE;
}
