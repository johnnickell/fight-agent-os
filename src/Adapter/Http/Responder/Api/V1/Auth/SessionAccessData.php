<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use Fight\AccessControl\Application\AccessControl\User\Security\TokenSet;
use Fight\Common\Domain\Type\Arrayable;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * Class SessionAccessData
 *
 * Projects only the memory access credential and conservative whole-second expiry
 */
final readonly class SessionAccessData implements Arrayable
{
    private SensitiveParameterValue $access;
    private int $expiry;

    /**
     * Constructs SessionAccessData
     */
    public function __construct(#[SensitiveParameter] TokenSet $tokens)
    {
        $this->access = new SensitiveParameterValue($tokens->getAccessToken()->toString());
        $this->expiry = $tokens->getAccessTokenExpiresAt()->getTimestamp();
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'access_token' => (string) $this->access->getValue(),
            'issued_at'    => $this->expiry - 900,
            'expires_at'   => $this->expiry
        ];
    }
}
