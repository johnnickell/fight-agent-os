<?php

declare(strict_types=1);

use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $rootDir = dirname(__DIR__, 2);
    $container['app.project_dir'] = $rootDir;
$container['app.bin_dir'] = sprintf('%s/bin', $rootDir);
$container['app.bootstrap_dir'] = sprintf('%s/bootstrap', $rootDir);
$container['app.config_dir'] = sprintf('%s/config', $rootDir);
$container['app.etc_dir'] = sprintf('%s/etc', $rootDir);
$container['app.planning_dir'] = sprintf('%s/planning', $rootDir);
$container['app.public_dir'] = sprintf('%s/public', $rootDir);
$container['app.scripts_dir'] = sprintf('%s/scripts', $rootDir);
$container['app.src_dir'] = sprintf('%s/src', $rootDir);
$container['app.tests_dir'] = sprintf('%s/tests', $rootDir);
    $container['app.vendor_dir'] = sprintf('%s/vendor', $rootDir);
    $container['app.storage_dir'] = sprintf('%s/var/storage', $rootDir);
    $container['app.database_url'] = getenv('DATABASE_URL') ?: 'sqlite:///:memory:';
};
