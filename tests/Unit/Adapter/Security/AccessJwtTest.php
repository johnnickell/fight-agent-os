<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Security;

use DateTimeImmutable;
use Fight\Common\Application\Auth\Exception\TokenException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessTokens;

/**
 * Proves strict issuance and independent cryptographic consumption
 */
final class AccessJwtTest extends TestCase
{
    /**
     * Verifies exact issuance, safe contents and unpredictable identifiers
     */
    public function testIssuance(): void
    {
        $fixture = new AccessTokens();
        $claims = $fixture->claims();
        $package = array_intersect_key($claims, array_flip(['sub', 'sid', 'type', 'auth_version', 'iat']));
        $expiration = new DateTimeImmutable('@'.$claims['exp']);
        $first = $fixture->jwt->decode($fixture->jwt->encode($package, $expiration));
        $second = $fixture->jwt->decode($fixture->jwt->encode($package, $expiration));
        self::assertSame(900, $first['exp'] - $first['iat']);
        self::assertSame($first['iat'], $first['nbf']);
        self::assertSame('https://agent-os.test', $first['iss']);
        self::assertSame(['agent-os-api'], $first['aud']);
        self::assertNotSame($first['jti'], $second['jti']);
        self::assertSame($package, array_intersect_key($first, $package));
        $keys = array_keys($first);
        sort($keys);
        self::assertSame(['aud', 'auth_version', 'exp', 'iat', 'iss', 'jti', 'nbf', 'sid', 'sub', 'type'], $keys);
    }

    /**
     * Normalizes only serialized expiry downward without mutating precise package time
     */
    #[DataProvider('expiryPrecision')]
    public function testFractionalExpirySerializesDownward(string $time): void
    {
        $fixture = new AccessTokens();
        $fixture->clock->time = new DateTimeImmutable($time);
        $claims = $fixture->claims();
        $package = array_intersect_key($claims, array_flip(['sub', 'sid', 'type', 'auth_version', 'iat']));
        $expiration = $fixture->clock->now()->modify('+900 seconds');
        $decoded = $fixture->jwt->decode($fixture->jwt->encode($package, $expiration));
        self::assertSame($package, array_intersect_key($decoded, $package));
        self::assertSame($package['iat'] + 900, $decoded['exp']);
        self::assertSame($expiration->getTimestamp(), $decoded['exp']);
        self::assertTrue(new DateTimeImmutable('@'.$decoded['exp']) <= $expiration);
        self::assertSame($time, $fixture->clock->now()->format('Y-m-d\TH:i:s.uP'));
        self::assertEquals($fixture->clock->now()->modify('+900 seconds'), $expiration);
        $fixture->clock->time = new DateTimeImmutable('@'.$decoded['exp'])->modify('-1 microsecond');
        self::assertSame($decoded, $fixture->jwt->decode($fixture->signed(json_encode($decoded, JSON_THROW_ON_ERROR))));
        $fixture->clock->time = new DateTimeImmutable('@'.$decoded['exp']);
        $this->assertRejected($fixture, $fixture->signed(json_encode($decoded, JSON_THROW_ON_ERROR)));
    }

    /**
     * Supplies whole, ordinary fractional, end-of-second and timezone-offset precision
     *
     * @return iterable<string, array{string}>
     */
    public static function expiryPrecision(): iterable
    {
        yield 'whole' => ['2026-09-28T12:00:00.000000+00:00'];
        yield 'fractional' => ['2026-09-28T12:00:00.123456+00:00'];
        yield 'end-of-second' => ['2026-09-28T12:00:00.999999+00:00'];
        yield 'offset' => ['2026-09-28T14:00:00.999999+02:00'];
    }

    /**
     * Rejects caller enrichment and inconsistent package expiry
     */
    public function testInvalidIssuance(): void
    {
        $fixture = new AccessTokens();
        $package = array_intersect_key($fixture->claims(), array_flip(['sub', 'sid', 'type', 'auth_version', 'iat']));
        foreach (['extra', 'expiry', 'short-fraction', 'long-fraction', 'missing', 'wrong-type'] as $case) {
            $claims = $package;
            $expiration = $fixture->clock->now()->modify('+900 seconds');
            if ($case === 'extra') {
                $claims['roles'] = ['admin'];
            } elseif ($case === 'expiry') {
                $expiration = $expiration->modify('+1 second');
            } elseif ($case === 'short-fraction') {
                $expiration = $expiration->modify('-1 microsecond');
            } elseif ($case === 'long-fraction') {
                $expiration = $expiration->modify('+1 second +1 microsecond');
            } elseif ($case === 'missing') {
                unset($claims['sid']);
            } else {
                $claims['auth_version'] = '1';
            }

            try {
                $fixture->jwt->encode($claims, $expiration);
                self::fail('Invalid issuance accepted: '.$case);
            } catch (TokenException $error) {
                self::assertSame('Access token rejected.', $error->getMessage());
                self::assertNull($error->getPrevious());
            }
        }
    }

    /**
     * Rejects absent, malformed, noncanonical and duplicate-equivalent claim members
     */
    #[DataProvider('claimCases')]
    public function testClaimMatrix(string $claim, string $case, mixed $value): void
    {
        $fixture = new AccessTokens();
        $claims = $fixture->claims();
        if ($case === 'missing') {
            unset($claims[$claim]);
        } elseif ($case !== 'duplicate') {
            $claims[$claim] = $value;
        }

        $json = json_encode($claims, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        if ($case === 'duplicate') {
            $escaped = '\\u'.sprintf('%04x', ord($claim[0])).substr($claim, 1);
            $json = substr($json, 0, -1).',"'.$escaped.'":'.json_encode($claims[$claim], JSON_THROW_ON_ERROR).'}';
        }

        $this->assertRejected($fixture, $fixture->signed($json));
    }

    /**
     * Enumerates every required claim's independent rejection classes
     *
     * @return iterable<string, array{string, string, mixed}>
     */
    public static function claimCases(): iterable
    {
        foreach (['iss', 'aud', 'sub', 'sid', 'jti', 'type', 'auth_version', 'iat', 'nbf', 'exp'] as $claim) {
            foreach (['missing', 'duplicate'] as $case) {
                yield $claim.'-'.$case => [$claim, $case, null];
            }

            foreach ([null, false, [], new \stdClass()] as $i => $value) {
                yield $claim.'-type-'.$i => [$claim, 'value', $value];
            }
        }

        $invalid = [
            'iss'          => ['', 'https://foreign.test'],
            'aud'          => ['agent-os-api', ['agent-os-api', 'other'], ['other']],
            'sub'          => ['', 'not-an-id'],
            'sid'          => ['', 'not-an-id'],
            'jti'          => ['', str_repeat('a', 31), str_repeat('A', 32), str_repeat('a', 33)],
            'type'         => ['', 'refresh'],
            'auth_version' => [0, -1, '1', 1.0],
            'iat'          => [0, -1, '1', 1.0, PHP_INT_MAX],
            'nbf'          => [0, '1', 1.0],
            'exp'          => [0, '1', 1.0, PHP_INT_MAX]
        ];
        foreach ($invalid as $claim => $values) {
            foreach ($values as $i => $value) {
                yield $claim.'-invalid-'.$i => [$claim, 'value', $value];
            }
        }
    }

    /**
     * Verifies future tolerance never extends the hard expiration boundary
     */
    public function testClockBoundaries(): void
    {
        $fixture = new AccessTokens();
        foreach ([-901, -900, -899, 0, 30, 31] as $offset) {
            $claims = $fixture->claims();
            $claims['iat'] += $offset;
            $claims['nbf'] = $claims['iat'];
            $claims['exp'] = $claims['iat'] + 900;
            $token = $fixture->signed(json_encode($claims, JSON_THROW_ON_ERROR));
            if (in_array($offset, [-899, 0, 30], true)) {
                self::assertSame($claims, $fixture->jwt->decode($token));
            } else {
                $this->assertRejected($fixture, $token);
            }
        }

        foreach (['nbf', 'exp'] as $field) {
            $claims = $fixture->claims();
            ++$claims[$field];
            $this->assertRejected($fixture, $fixture->signed(json_encode($claims, JSON_THROW_ON_ERROR)));
        }
    }

    /**
     * Rejects algorithm, header, signature, syntax and private-claim attacks
     */
    public function testHeaderAndTokenMatrix(): void
    {
        $fixture = new AccessTokens();
        $payload = json_encode($fixture->claims(), JSON_THROW_ON_ERROR);
        foreach (
            [
            '{"alg":"none","kid":"active","typ":"JWT"}',
            '{"alg":"HS512","kid":"active","typ":"JWT"}',
            '{"alg":"HS256","kid":"unknown","typ":"JWT"}',
            '{"alg":"HS256","typ":"JWT"}',
            '{"alg":"HS256","kid":"active","typ":"refresh"}',
            '{"alg":"HS256","kid":"active","typ":"JWT","crit":[]}',
            '{"alg":"HS256","kid":"active","typ":"JWT","jku":"https://foreign.test"}',
            '{"alg":"none","alg":"HS256","kid":"active","typ":"JWT"}',
            '{"alg":"HS256","kid":[],"typ":"JWT"}'
            ] as $header
        ) {
            $this->assertRejected($fixture, $fixture->signed($payload, $header));
        }

        foreach (['roles', 'permissions', 'email', 'password', 'refresh_credential', 'private'] as $field) {
            $claims = $fixture->claims();
            $claims[$field] = 'PRIVATE_CLAIM_MARKER';
            $this->assertRejected($fixture, $fixture->signed(json_encode($claims, JSON_THROW_ON_ERROR)));
        }

        $valid = $fixture->signed($payload);
        foreach (
            ['', 'a.b', 'a.b.c.d', str_repeat('x', 2049), $valid.'=', ' '.$valid, 'a.b.c',
            substr($valid, 0, -43).str_repeat('a', 43), $fixture->signed('{'), $fixture->signed('[]'),
            $fixture->signed('{"sub":"x",}')
            ] as $token
        ) {
            $this->assertRejected($fixture, $token);
        }
    }

    /**
     * Verifies active signing and finite previous-key overlap
     */
    public function testRotation(): void
    {
        $fixture = new AccessTokens();
        $claims = $fixture->claims();
        $old = $fixture->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        $switch = $fixture->clock->now()->getTimestamp();
        $newSecret = random_bytes(32);
        $config = $fixture->configuration;
        $config['active_kid'] = 'new';
        $config['keys'] = [
            ['kid' => 'new', 'secret_hex' => bin2hex($newSecret)],
            [
                'kid'            => 'active',
                'secret_hex'     => bin2hex($fixture->secret),
                'last_issued_at' => $switch,
                'verify_until'   => $switch + 900
            ]
        ];
        $rotated = $fixture->consumer($config);
        self::assertSame($claims, $rotated->decode($old));
        $fixture->clock->time = $fixture->clock->time->modify('+1 second');
        $claims['iat'] = $claims['nbf'] = $switch + 1;
        $claims['exp'] = $switch + 901;
        try {
            $rotated->decode($fixture->signed(json_encode($claims, JSON_THROW_ON_ERROR)));
            self::fail('Previous key issued after retirement.');
        } catch (TokenException $error) {
            self::assertSame('Access token rejected.', $error->getMessage());
        }

        $package = array_intersect_key($claims, array_flip(['sub', 'sid', 'type', 'auth_version', 'iat']));
        $new = $rotated->encode($package, new DateTimeImmutable('@'.$claims['exp']));
        self::assertSame($claims['iat'], $rotated->decode($new)['iat']);
        $this->assertRejected($fixture, $new);
        $fixture->clock->time = new DateTimeImmutable('@'.($switch + 899));
        self::assertSame($switch, $rotated->decode($old)['iat']);
        $fixture->clock->time = new DateTimeImmutable('@'.($switch + 900));
        $this->expectException(TokenException::class);
        $rotated->decode($old);
    }

    /**
     * Requires one sanitized error without retaining sensitive causes
     */
    private function assertRejected(AccessTokens $fixture, string $token): void
    {
        try {
            $fixture->jwt->decode($token);
            self::fail('Invalid access token accepted.');
        } catch (TokenException $error) {
            self::assertSame('Access token rejected.', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
    }
}
