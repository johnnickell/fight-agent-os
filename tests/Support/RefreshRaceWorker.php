<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Security\Activation\RandomRefreshCredentials;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\RefreshCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Throwable;

/**
 * Class RefreshRaceWorker
 *
 * Holds an actual HTTP refresh after authority capture but before PostgreSQL compare-and-set
 */
final class RefreshRaceWorker
{
    /**
     * Runs one independently connected contender without exporting any secret or exception detail
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
            $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
            $container = $app->getContainer();
            $connection = $container->get(Connection::class);
            $connection->executeStatement("SET statement_timeout = '10s'");
            $connection->executeStatement("SET lock_timeout = '5s'");
            $tokens = new AccessTokens();
            $tokens->clock->time = new DateTimeImmutable($input['time']);
            $container->set(Clock::class, static fn(): Clock => $tokens->clock);
            $container->set(TokenEncoder::class, static fn(): TokenEncoder => $tokens->jwt);
            $generator = new class implements RefreshCredentialGenerator {
                /**
                 * @inheritDoc
                 */
                public function generate(): RefreshCredential
                {
                    echo "ready\n";
                    flush();
                    if (trim((string) fgets(STDIN)) !== 'go') {
                        throw new RuntimeException('Barrier was not released.');
                    }

                    return new RandomRefreshCredentials()->generate();
                }
            };
            $container->set(RefreshCredentialGenerator::class, static fn(): RefreshCredentialGenerator => $generator);
            $request = new ServerRequestFactory()->createServerRequest(
                'POST',
                'https://agent-os.test/api/v1/auth/refresh',
                ['REMOTE_ADDR' => '127.0.0.1']
            );
            $cookies = CsrfCookie::NAME.'='.$input['nonce'].'; ';
            $cookies .= BrowserSessionCookies::REFRESH_NAME.'='.$input['credential'];
            $headers = [
                'Content-Type' => 'application/json',
                'Origin'       => 'https://agent-os.test',
                'X-CSRF-Proof' => $input['proof'],
                'Cookie'       => $cookies
            ];
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
            $request->getBody()->write('{}');
            echo $app->handle($request)->getStatusCode()."\n";
        } catch (Throwable) {
            echo "unexpected\n";
            exit(1);
        }
    }
}
