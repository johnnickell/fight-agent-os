<?php

declare(strict_types=1);

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

require dirname(__DIR__).'/vendor/autoload.php';

$action = $argv[1] ?? 'check';
if (!in_array($action, ['check', 'reset'], true)) {
    fwrite(STDERR, "Usage: php scripts/guarded_test_database.php [check|reset]\n");
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
} else {
    $version = $connection->fetchOne('SHOW server_version');
    fwrite(STDOUT, sprintf("Guarded test database check passed on PostgreSQL %s.\n", $version));
}
