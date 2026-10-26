<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Auth;

use App\Application\Sessions\BrowserFamily;
use App\Application\Sessions\DeviceClass;
use App\Application\Sessions\DeviceDescriptor;
use App\Application\Sessions\SessionPresentation;
use Fight\AccessControl\Application\AccessControl\User\Security\TokenSet;
use Psr\Http\Message\ServerRequestInterface;
use SensitiveParameter;
use Throwable;

/**
 * Class CaptureSessionPresentation
 *
 * Captures bounded untrusted presentation after successful session creation without changing authority
 */
final readonly class CaptureSessionPresentation
{
    /**
     * Constructs CaptureSessionPresentation
     */
    public function __construct(private SessionPresentation $presentation)
    {
    }

    /**
     * Parses at most 512 printable ASCII bytes locally without retaining versions or raw input
     */
    public static function parse(#[SensitiveParameter] string $header): DeviceDescriptor
    {
        if ($header === '' || strlen($header) > 512 || preg_match('/[^\x20-\x7e]/', $header) === 1) {
            return new DeviceDescriptor();
        }
        $browser = match (true) {
            preg_match('~\bEdg(?:A|iOS)?/[0-9]~', $header) === 1 => BrowserFamily::EDGE,
            preg_match('~\b(?:Firefox|FxiOS)/[0-9]~', $header) === 1 => BrowserFamily::FIREFOX,
            preg_match('~\b(?:Chrome|CriOS)/[0-9]~', $header) === 1 => BrowserFamily::CHROME,
            preg_match('~\bVersion/[0-9].*\bSafari/[0-9]~', $header) === 1 => BrowserFamily::SAFARI,
            default => BrowserFamily::UNKNOWN
        };
        $device = match (true) {
            preg_match('~\b(?:iPad|Tablet)\b~i', $header) === 1 => DeviceClass::TABLET,
            str_contains($header, 'Android') && !str_contains($header, 'Mobile') => DeviceClass::TABLET,
            preg_match('~\b(?:Mobile|iPhone|Android)\b~', $header) === 1 => DeviceClass::MOBILE,
            preg_match('~\b(?:Windows NT|Macintosh|X11)\b~', $header) === 1 => DeviceClass::DESKTOP,
            default => DeviceClass::UNKNOWN
        };

        return new DeviceDescriptor($browser, $device);
    }

    /**
     * Records only fixed enums and suppresses metadata failures without logging private request material
     */
    public function capture(#[SensitiveParameter] TokenSet $tokens, ServerRequestInterface $request): void
    {
        $headers = $request->getHeader('User-Agent');
        $device = count($headers) === 1 ? self::parse($headers[0]) : new DeviceDescriptor();
        try {
            $this->presentation->capture($tokens->getRefreshSessionId(), $device, $tokens->getRefreshExpiresAt());
        } catch (Throwable) {
            // Session creation already committed. Presentation can never invalidate or extend it.
        }
    }
}
