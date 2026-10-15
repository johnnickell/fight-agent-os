<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use App\Application\Mail\MailMessage;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use SensitiveParameter;

/**
 * Class InvitationMailTemplate
 *
 * Renders the fixed invitation-v1 text and HTML alternatives without remote content
 */
final readonly class InvitationMailTemplate
{
    /**
     * Constructs InvitationMailTemplate
     */
    public function __construct(private CanonicalActivationLink $link)
    {
    }

    /**
     * Renders only during the claimed provider invocation
     */
    public function render(#[SensitiveParameter] CredentialDeliveryInvocation $invocation): MailMessage
    {
        $url = $this->link->forCredential($invocation->getCredential());
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = <<<TEXT
You have been invited to Fight Agent OS.

Activate your account:
{$url}

This single-use link expires seven days after the invitation was issued, not after this email arrives.
If it has expired or no longer works, ask your administrator for a new invitation.
If you did not expect this invitation, ignore it. Do not forward or share this link.
TEXT;
        $html = <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="referrer" content="no-referrer">
<meta http-equiv="Content-Security-Policy" content="default-src &apos;none&apos;">
<title>Fight Agent OS invitation</title></head><body>
<h1>You have been invited to Fight Agent OS</h1>
<p><a href="{$escapedUrl}">Activate your account</a></p>
<p>This single-use link expires seven days after the invitation was issued, not after this email arrives.</p>
<p>If it has expired or no longer works, ask your administrator for a new invitation.</p>
<p>If you did not expect this invitation, ignore it. Do not forward or share this link.</p>
</body></html>
HTML;

        return new MailMessage(
            $invocation->getIdempotencyId(),
            $invocation->getEmail(),
            'Your Fight Agent OS invitation',
            $text,
            $html
        );
    }
}
