<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Auth;

use App\Application\Failure\RateLimited;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SensitiveParameter;

/**
 * Class ActivationAdmission
 *
 * Commits bounded secret-safe network and endpoint attempts before activation
 */
final readonly class ActivationAdmission implements MiddlewareInterface
{
    private string $key;

    /**
     * Constructs ActivationAdmission
     */
    public function __construct(
        private Connection $connection,
        private Clock $clock,
        #[SensitiveParameter] string $hexKey
    ) {
        if (!preg_match('/\A[0-9a-f]{64}\z/D', $hexKey)) {
            throw new InvalidArgumentException('Invalid activation admission key.');
        }
        $key = (string) hex2bin($hexKey);
        if (count(array_unique(str_split($key))) < 16) {
            throw new InvalidArgumentException('Invalid activation admission key.');
        }
        $this->key = $key;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $network = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        if (!is_string($network) || filter_var($network, FILTER_VALIDATE_IP) === false) {
            throw new RateLimited(60);
        }
        if ($this->connection->isTransactionActive()) {
            throw new LogicException('Activation admission requires an independent transaction.');
        }
        $this->connection->transactional(function () use ($network): void {
            $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24121, 43)');
            $now = $this->clock->now()->getTimestamp();
            $end = (int) (floor($now / 900) + 1) * 900;
            $this->connection->executeStatement('DELETE FROM activation_rate_limits WHERE window_end <= ?', [$now]);
            $keys = [
                hash_hmac('sha256', 'endpoint:activation', $this->key)          => 300,
                hash_hmac('sha256', 'network:'.inet_pton($network), $this->key) => 10
            ];
            $missing = 0;
            foreach ($keys as $key => $limit) {
                $attempts = $this->connection->fetchOne(
                    'SELECT attempts FROM activation_rate_limits WHERE key = ?',
                    [$key]
                );
                if ($attempts === false) {
                    ++$missing;
                } elseif ((int) $attempts >= $limit) {
                    throw new RateLimited($end - $now);
                }
            }
            if ((int) $this->connection->fetchOne('SELECT count(*) FROM activation_rate_limits') + $missing > 10000) {
                throw new RateLimited($end - $now);
            }
            foreach (array_keys($keys) as $key) {
                $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO activation_rate_limits (key, window_end, attempts) VALUES (?, ?, 1)
ON CONFLICT (key) DO UPDATE SET attempts = activation_rate_limits.attempts + 1
SQL,
                    [$key, $end]
                );
            }
        });

        return $handler->handle($request);
    }
}
