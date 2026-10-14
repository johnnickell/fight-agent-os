<?php

declare(strict_types=1);

namespace App\Adapter\Security\AccessToken;

use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Auth\Exception\TokenException;
use Fight\Common\Application\Auth\Security\TokenDecoder;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use SensitiveParameter;
use Throwable;

/**
 * Class AccessJwt
 *
 * Issues and independently consumes the bounded ADR 0003 compact JWT profile
 */
final readonly class AccessJwt implements TokenEncoder, TokenDecoder
{
    public const int MAX_TOKEN_BYTES = 2048;

    private const array PACKAGE_CLAIMS = ['auth_version', 'iat', 'sid', 'sub', 'type'];
    private const array CLAIMS = ['aud', 'auth_version', 'exp', 'iat', 'iss', 'jti', 'nbf', 'sid', 'sub', 'type'];

    /**
     * Constructs AccessJwt
     */
    public function __construct(private AccessKeyring $keyring, private Clock $clock)
    {
    }

    /**
     * @inheritDoc
     */
    public function encode(#[SensitiveParameter] array $claims, DateTimeImmutable $expiration): string
    {
        try {
            $this->assertKeys($claims, self::PACKAGE_CLAIMS);
            $claims += [
                'iss' => $this->keyring->issuer,
                'aud' => [$this->keyring->audience],
                'jti' => bin2hex(random_bytes(16)),
                'nbf' => $claims['iat'],
                // Normalize only JWT expiry downward; package/session clocks retain full precision.
                'exp' => $expiration->getTimestamp()
            ];
            $this->assertClaims($claims);
            $header = self::base64Url(json_encode([
                'alg' => 'HS256', 'kid' => $this->keyring->activeKid, 'typ' => 'JWT'
            ], JSON_THROW_ON_ERROR));
            $payload = self::base64Url(json_encode($claims, JSON_THROW_ON_ERROR));
            $message = $header.'.'.$payload;

            return $message.'.'.self::base64Url($this->keyring->sign($message));
        } catch (Throwable) {
            throw new TokenException('Access token rejected.');
        }
    }

    /**
     * @inheritDoc
     */
    public function decode(#[SensitiveParameter] string $token): array
    {
        try {
            if (strlen($token) > self::MAX_TOKEN_BYTES || substr_count($token, '.') !== 2) {
                throw new TokenException();
            }

            [$headerPart, $payloadPart, $signaturePart] = explode('.', $token);
            $header = $this->object(self::decodePart($headerPart));
            $this->assertKeys($header, ['alg', 'kid', 'typ']);
            if (
                $header['alg'] !== 'HS256'
                || $header['typ'] !== 'JWT'
                || !is_string($header['kid'])
                || preg_match('/\A[A-Za-z0-9_-]{1,64}\z/D', $header['kid']) !== 1
            ) {
                throw new TokenException();
            }

            $claims = $this->object(self::decodePart($payloadPart));
            $this->assertClaims($claims);
            $signature = self::decodePart($signaturePart);
            if (
                strlen($signature) !== 32
                || !$this->keyring->verify(
                    $header['kid'],
                    $headerPart.'.'.$payloadPart,
                    $signature,
                    $claims['iat'],
                    $this->clock->now()->getTimestamp()
                )
            ) {
                throw new TokenException();
            }

            return $claims;
        } catch (Throwable) {
            // No previous exception: parser, claims, configuration and signature never enter diagnostics.
            throw new TokenException('Access token rejected.');
        }
    }

    /**
     * Encodes canonical unpadded base64url
     */
    private static function base64Url(#[SensitiveParameter] string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Rejects padding and noncanonical base64url encodings
     */
    private static function decodePart(#[SensitiveParameter] string $value): string
    {
        if ($value === '' || preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) !== 1) {
            throw new TokenException();
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false || self::base64Url($decoded) !== $value) {
            throw new TokenException();
        }

        return $decoded;
    }

    /**
     * Validates all required claims without coercing types or extending expiry
     *
     * @param array<string, mixed> $claims
     */
    private function assertClaims(#[SensitiveParameter] array $claims): void
    {
        $this->assertKeys($claims, self::CLAIMS);
        if (
            $claims['iss'] !== $this->keyring->issuer
            || $claims['aud'] !== [$this->keyring->audience]
            || $claims['type'] !== 'access'
            || !is_string($claims['sub'])
            || !is_string($claims['sid'])
            || UserId::fromString($claims['sub'])->toString() !== $claims['sub']
            || RefreshSessionId::fromString($claims['sid'])->toString() !== $claims['sid']
            || !is_string($claims['jti'])
            || preg_match('/\A[a-f0-9]{32}\z/D', $claims['jti']) !== 1
            || !is_int($claims['auth_version'])
            || $claims['auth_version'] < 1
            || !is_int($claims['iat'])
            || !is_int($claims['nbf'])
            || !is_int($claims['exp'])
        ) {
            throw new TokenException();
        }

        $now = $this->clock->now()->getTimestamp();
        if (
            $claims['iat'] < 1
            || $claims['iat'] > PHP_INT_MAX - 900
            || $claims['nbf'] !== $claims['iat']
            || $claims['exp'] !== $claims['iat'] + 900
            || $claims['iat'] > $now + 30
            || $claims['iat'] <= $now - 900
            || $now >= $claims['exp']
        ) {
            throw new TokenException();
        }
    }

    /**
     * Rejects missing and unknown members
     *
     * @param array<string, mixed> $object
     * @param array<string> $expected
     *
     * @phpstan-param list<string> $expected
     */
    private function assertKeys(#[SensitiveParameter] array $object, array $expected): void
    {
        $keys = array_keys($object);
        sort($keys);
        if ($keys !== $expected) {
            throw new TokenException();
        }
    }

    /**
     * Parses only bounded flat JWT objects and detects escaped duplicate member names
     *
     * @return array<string, mixed>
     */
    private function object(#[SensitiveParameter] string $json): array
    {
        // Decode for UTF-8, JSON grammar and exact scalar types, but never rely on last-member-wins.
        $decoded = json_decode($json, false, 4, JSON_THROW_ON_ERROR);
        if (!$decoded instanceof \stdClass) {
            throw new TokenException();
        }

        $string = '"(?:[^"\\\\\x00-\x1f]|\\\\(?:["\\\\\/bfnrt]|u[0-9a-fA-F]{4}))*"';
        $scalar = '(?:'.$string.'|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?|true|false|null)';
        $value = '(?:'.$scalar.'|\[\s*(?:'.$scalar.'(?:\s*,\s*'.$scalar.')*)?\s*\])';
        $pattern = '~\G\s*('.$string.')\s*:\s*'.$value.'\s*([,}])~';
        if (preg_match('/\A\s*\{/', $json, $opening) !== 1) {
            throw new TokenException();
        }

        $offset = strlen($opening[0]);
        $seen = [];
        do {
            if (preg_match($pattern, $json, $member, 0, $offset) !== 1) {
                throw new TokenException();
            }

            $name = json_decode($member[1], true, 2, JSON_THROW_ON_ERROR);
            if (!is_string($name) || isset($seen[$name])) {
                throw new TokenException();
            }

            $seen[$name] = true;
            $offset += strlen($member[0]);
        } while ($member[2] === ',');
        if (trim(substr($json, $offset)) !== '') {
            throw new TokenException();
        }

        return get_object_vars($decoded);
    }
}
