<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Security\AccessToken\AccessJwt;
use App\Adapter\Security\AccessToken\AccessKeyring;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class AccessTokens
 *
 * Supplies ephemeral signing material and independently signed adversarial inputs
 */
final class AccessTokens
{
    public string $secret;
    public AccessJwt $jwt;
    public AccessClock $clock;
    /** @var array<string, mixed> */
    public array $configuration;

    /**
     * Constructs AccessTokens
     */
    public function __construct()
    {
        $this->secret = random_bytes(32);
        $this->clock = new AccessClock();
        $this->configuration = [
            'algorithm'  => 'HS256',
            'issuer'     => 'https://agent-os.test',
            'audience'   => 'agent-os-api',
            'active_kid' => 'active',
            'keys'       => [['kid' => 'active', 'secret_hex' => bin2hex($this->secret)]]
        ];
        $this->jwt = $this->consumer($this->configuration);
    }

    /**
     * Creates a consumer with a selected rotation configuration
     *
     * @param array<string, mixed> $configuration
     */
    public function consumer(array $configuration): AccessJwt
    {
        return new AccessJwt(
            new AccessKeyring(json_encode($configuration, JSON_THROW_ON_ERROR), $this->clock),
            $this->clock
        );
    }

    /**
     * Returns the expected profile independently of the encoder
     *
     * @return array<string, mixed>
     */
    public function claims(): array
    {
        $now = $this->clock->now()->getTimestamp();

        return [
            'iss'          => 'https://agent-os.test',
            'aud'          => ['agent-os-api'],
            'sub'          => UserId::generate()->toString(),
            'sid'          => RefreshSessionId::generate()->toString(),
            'jti'          => bin2hex(random_bytes(16)),
            'type'         => 'access',
            'auth_version' => 1,
            'iat'          => $now,
            'nbf'          => $now,
            'exp'          => $now + 900
        ];
    }

    /**
     * Creates raw JSON signatures without passing through owned issuance validation
     */
    public function signed(string $payload, string $header = '{"alg":"HS256","kid":"active","typ":"JWT"}'): string
    {
        $encode = static fn(string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $message = $encode($header).'.'.$encode($payload);

        return $message.'.'.$encode(hash_hmac('sha256', $message, $this->secret, true));
    }
}
