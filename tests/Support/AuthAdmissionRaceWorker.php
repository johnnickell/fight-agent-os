<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Http\Api\V1\Auth\AuthOperation;
use App\Adapter\Persistence\Authentication\PostgresAuthAttempts;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Security\Authentication\AuthAdmission;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Class AuthAdmissionRaceWorker
 *
 * Competes through an independently connected production admission adapter with bounded pipe output
 */
final class AuthAdmissionRaceWorker
{
    /**
     * Reports readiness and then only an admitted or denied outcome
     */
    public static function run(): void
    {
        $input = json_decode((string) fgets(STDIN), true, 16, JSON_THROW_ON_ERROR);
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $connection = DriverManager::getConnection(new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ])->parse($url));
        $guard->assertConnected($connection, $expected);
        $endpointOnly = str_starts_with($input['operation'], 'endpoint:');
        $endpointLimit = $endpointOnly || $input['operation'] === 'reset_endpoint' ? 1 : 3;
        if ($input['operation'] === 'reset_email') {
            $endpointLimit = 300;
        }
        $admission = new AuthAdmission(
            new PostgresAuthAttempts($connection, new AccessClock(new DateTimeImmutable($input['now']))),
            $input['key'],
            $endpointOnly || $input['operation'] === 'reset_email' || $input['operation'] === 'reset_endpoint' ? 20 : 1,
            $endpointLimit
        );
        fwrite(STDOUT, $connection->fetchOne('SELECT pg_backend_pid()')."\n");
        fflush(STDOUT);
        $allowed = false;
        if ($input['operation'] === 'email') {
            $allowed = $admission->allows(EmailAddress::fromString('race@example.test'));
        } else {
            try {
                if (str_starts_with($input['operation'], 'reset_')) {
                    $admission->passwordReset(EmailAddress::fromString('race@example.test'), '192.0.2.1');
                } else {
                    $operation = $endpointOnly ? substr($input['operation'], 9) : $input['operation'];
                    $admission->endpoint(AuthOperation::from($operation), '192.0.2.1');
                }
                $allowed = true;
            } catch (RateLimitExceeded) {
                // Return only the safe admission outcome.
            }
        }
        fwrite(STDOUT, ($allowed ? 'admitted' : 'denied')."\n");
        $connection->close();
    }
}
