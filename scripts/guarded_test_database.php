<?php

declare(strict_types=1);

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

require dirname(__DIR__).'/vendor/autoload.php';

$action = $argv[1] ?? 'check';
if (!in_array($action, ['check', 'reset', 'fingerprint', 'reuse-check'], true)) {
    fwrite(STDERR, "Usage: php scripts/guarded_test_database.php [check|reset|fingerprint|reuse-check]\n");
    exit(2);
}

$databaseUrl = getenv('TEST_DATABASE_URL');
$allowedHost = getenv('TEST_DATABASE_ALLOWED_HOST');
$environment = getenv('APP_ENV');
if ($databaseUrl === false || $databaseUrl === '' || $allowedHost === false || $allowedHost === '') {
    throw new RuntimeException('Guarded test database configuration is incomplete.');
}

$guard = new DatabaseTargetGuard(array_values(array_filter(array_map('trim', explode(',', $allowedHost)))));
$expected = $guard->assertConfigured($environment === false ? '' : $environment, $databaseUrl);
$connection = DriverManager::getConnection((new DsnParser([
    'postgres'   => 'pdo_pgsql',
    'postgresql' => 'pdo_pgsql'
]))->parse($databaseUrl));
$guard->assertConnected($connection, $expected);

if ($action === 'reset') {
    $connection->executeStatement('DROP SCHEMA public CASCADE');
    $connection->executeStatement('CREATE SCHEMA public AUTHORIZATION CURRENT_USER');
    fwrite(STDOUT, "Guarded test schema reset completed.\n");
} elseif ($action === 'fingerprint') {
    // Read-only identity and isolation checks run even when the expensive suite is reused.
    $identity = $connection->fetchAssociative(
        <<<'SQL'
SELECT version(), current_database(), current_user, inet_server_addr(), inet_server_port(),
    pg_postmaster_start_time(), (SELECT oid FROM pg_database WHERE datname = current_database()) AS database_oid
SQL
    );
    $settings = $connection->fetchAllAssociative('SELECT name, setting FROM pg_settings ORDER BY name');
    $database = $connection->fetchAssociative(
        <<<'SQL'
SELECT datdba, encoding, datcollate, datctype, datallowconn, datconnlimit, datacl::text
FROM pg_database WHERE datname = current_database()
SQL
    );
    $role = $connection->fetchAssociative(
        <<<'SQL'
SELECT oid, rolsuper, rolinherit, rolcreaterole, rolcreatedb, rolcanlogin, rolreplication,
    rolbypassrls, rolconnlimit, rolvaliduntil::text, rolconfig
FROM pg_roles WHERE rolname = current_user
SQL
    );
    fwrite(STDOUT, hash('sha256', json_encode([$identity, $settings, $database, $role], JSON_THROW_ON_ERROR))."\n");
} elseif ($action === 'reuse-check') {
    $clean = $connection->fetchOne(
        <<<'SQL'
SELECT EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'public' AND nspowner =
    (SELECT oid FROM pg_roles WHERE rolname = current_user))
AND NOT EXISTS (SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public')
SQL
    );
    if (!$clean) {
        throw new RuntimeException('Test schema changed since the completed backend cleanup.');
    }
    fwrite(STDOUT, "Guarded clean test schema verified.\n");
} else {
    $version = $connection->fetchOne('SHOW server_version');
    fwrite(STDOUT, sprintf("Guarded test database check passed on PostgreSQL %s.\n", $version));
}
