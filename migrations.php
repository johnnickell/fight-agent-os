<?php

declare(strict_types=1);

return [
    'table_storage'           => [
        'table_name' => 'doctrine_migration_versions'
    ],
    'migrations_paths'        => [
        'DoctrineMigrations' => __DIR__.'/database/migrations'
    ],
    'all_or_nothing'          => true,
    'check_database_platform' => true
];
