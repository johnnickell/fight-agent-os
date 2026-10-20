<?php

declare(strict_types=1);

namespace App\Adapter\Security\Authentication;

use App\Adapter\Http\Api\V1\Auth\AuthOperation;
use App\Adapter\Persistence\Authentication\PostgresAuthAttempts;
use App\Application\Failure\RateLimited;
use Fight\AccessControl\Application\AccessControl\User\Service\LoginThrottle;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Class AuthAdmission
 *
 * Consumes canonical-email and trusted-peer budgets independently of authentication results
 */
final readonly class AuthAdmission implements LoginThrottle
{
    private string $key;

    /**
     * Constructs AuthAdmission
     */
    public function __construct(
        private PostgresAuthAttempts $attempts,
        #[SensitiveParameter] string $hexKey,
        private int $networkLimit = 20,
        private int $endpointLimit = 300
    ) {
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $hexKey) !== 1
            || count(array_unique(str_split((string) hex2bin($hexKey)))) < 16
            || $networkLimit < 1 || $networkLimit > 20 || $endpointLimit < 1 || $endpointLimit > 300
        ) {
            throw new InvalidArgumentException('Invalid authentication admission configuration.');
        }
        $this->key = (string) hex2bin($hexKey);
    }

    /**
     * Records one of five rolling fifteen-minute attempts regardless of identity or eventual login result
     */
    public function allows(#[SensitiveParameter] EmailAddress $email): bool
    {
        try {
            $this->attempts->consume([hash_hmac('sha256', 'email:'.$email->canonical(), $this->key) => 5], 900);
        } catch (RateLimited) {
            return false;
        }

        return true;
    }

    /**
     * Validates a server-supplied peer address and records a closed endpoint purpose
     */
    public function endpoint(AuthOperation $operation, string $network): void
    {
        if (filter_var($network, FILTER_VALIDATE_IP) === false) {
            throw new RateLimited(60);
        }
        $endpointKey = hash_hmac('sha256', 'endpoint:'.$operation->value, $this->key);
        $peerKey = hash_hmac('sha256', 'network:'.$operation->value.':'.inet_pton($network), $this->key);
        $this->attempts->consume([$endpointKey => $this->endpointLimit, $peerKey => $this->networkLimit], 900);
    }

    /**
     * Returns no key or connection details to diagnostic snapshots
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['configuration' => '[redacted]'];
    }
}
