<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\User\Exception\LoginRejectedException;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * Class LoginRaceWorker
 *
 * Holds a real login after authority capture and before the package atomic persistence boundary
 */
final class LoginRaceWorker
{
    /**
     * Executes a bounded snapshot barrier without exporting tokens, credentials or exception detail
     */
    public static function run(): void
    {
        try {
            $input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
            $url = (string) getenv('TEST_DATABASE_URL');
            $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
            $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
            $connection = DriverManager::getConnection(new DsnParser([
                'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
            ])->parse($url));
            $guard->assertConnected($connection, $expected);
            $connection->executeStatement("SET statement_timeout = '10s'");
            $connection->executeStatement("SET lock_timeout = '5s'");
            putenv('DATABASE_URL='.$url);
            $container = require dirname(__DIR__, 2).'/config/services.php';
            $container->set(Connection::class, static fn(): Connection => $connection);
            $encoder = new class ($container->get(TokenEncoder::class)) implements TokenEncoder {
                /**
                 * Constructs the independently controlled issuance barrier
                 */
                public function __construct(private readonly TokenEncoder $inner)
                {
                }

                /**
                 * @inheritDoc
                 */
                public function encode(#[SensitiveParameter] array $claims, DateTimeImmutable $expiration): string
                {
                    $encoded = $this->inner->encode($claims, $expiration);
                    echo "ready\n";
                    flush();
                    if (trim((string) fgets(STDIN)) !== 'go') {
                        throw new RuntimeException('The barrier was not released.');
                    }

                    return $encoded;
                }
            };
            $container->set(TokenEncoder::class, static fn(): TokenEncoder => $encoder);
            try {
                $container->get(AuthenticationService::class)->login('login@example.test', $input['password'], false);
                echo "committed\n";
            } catch (LoginRejectedException) {
                echo "rejected\n";
            }
        } catch (Throwable) {
            echo "unexpected\n";
            exit(1);
        }
    }
}
