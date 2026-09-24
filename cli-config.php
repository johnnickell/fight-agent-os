<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;

require_once __DIR__ . '/vendor/autoload.php';

$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl === false || $databaseUrl === '') {
    throw new RuntimeException('DATABASE_URL must be configured.');
}

$connection = DriverManager::getConnection((new DsnParser([
    'postgres' => 'pdo_pgsql',
    'postgresql' => 'pdo_pgsql',
]))->parse($databaseUrl));

return DependencyFactory::fromConnection(
    new ConfigurationArray(require __DIR__ . '/migrations.php'),
    new ExistingConnection($connection)
);
