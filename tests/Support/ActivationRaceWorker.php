<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Application\Failure\StateConflict;
use App\Application\Security\Activation\ActivateInvitation;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use RuntimeException;
use Throwable;

/**
 * Class ActivationRaceWorker
 *
 * Coordinates independent activation transactions without exporting secrets or diagnostics
 */
final class ActivationRaceWorker
{
    /**
     * Executes the production use case behind a bounded snapshot barrier
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
            if (($input['mode'] ?? '') === 'admission') {
                $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
                $container = $app->getContainer();
                $nonce = bin2hex(random_bytes(32));
                $proof = $container->get(\App\Adapter\Security\HmacCsrfProofs::class)->sign($nonce, time() + 900);
                $request = new \Slim\Psr7\Factory\ServerRequestFactory()->createServerRequest(
                    'POST',
                    'https://agent-os.test/api/v1/auth/activate',
                    ['REMOTE_ADDR' => '127.0.0.1']
                )->withHeader('Content-Type', 'application/json')
                    ->withHeader('Origin', 'https://agent-os.test')
                    ->withHeader('X-CSRF-Proof', $proof)
                    ->withHeader('Cookie', '__Secure-agent_os_csrf='.$nonce);
                $request->getBody()->write(json_encode([
                    'user_id'               => $input['user'],
                    'credential'            => 'invalid',
                    'password'              => $input['password'],
                    'password_confirmation' => $input['password']
                ], JSON_THROW_ON_ERROR));
                echo "ready\n";
                flush();
                if (trim((string) fgets(STDIN)) !== 'go') {
                    throw new RuntimeException('The barrier was not released.');
                }
                echo $app->handle($request)->getStatusCode()."\n";

                return;
            }
            $container = require dirname(__DIR__, 2).'/config/services.php';
            $container->set(Connection::class, static fn(): Connection => $connection);
            $inner = $container->get(ActivationGrantRepository::class);
            $waited = false;
            $barrier = new RecoveryGrantBarrier($inner, static function () use (&$waited): void {
                if ($waited) {
                    return;
                }
                $waited = true;
                echo "ready\n";
                flush();
                if (trim((string) fgets(STDIN)) !== 'go') {
                    throw new RuntimeException('The barrier was not released.');
                }
            });
            $container->set(ActivationGrantRepository::class, static fn(): ActivationGrantRepository => $barrier);
            try {
                $container->get(ActivateInvitation::class)->activate(
                    UserId::fromString($input['user']),
                    $input['credential'],
                    $input['password']
                );
                echo "committed\n";
            } catch (StateConflict) {
                echo "conflict\n";
            }
        } catch (Throwable) {
            echo "unexpected\n";
            exit(1);
        }
    }
}
