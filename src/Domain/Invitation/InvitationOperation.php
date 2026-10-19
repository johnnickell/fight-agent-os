<?php

declare(strict_types=1);

namespace App\Domain\Invitation;

/**
 * Enum InvitationOperation
 *
 * Bounds the non-secret invitation limiter key space
 */
enum InvitationOperation: string
{
    case ISSUE = 'issue';
    case STATUS = 'status';
    case RETRY = 'retry';
    case RESEND = 'resend';
    case CORRECT = 'correct';
}
