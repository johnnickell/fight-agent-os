<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(Connection::class, static function (Container $container): Connection {
        return DriverManager::getConnection((new DsnParser(['sqlite' => 'pdo_sqlite']))->parse($container['app.database_url']));
    });
    $container->set(EntityManagerInterface::class, static function (Container $container): EntityManagerInterface {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);

        return new EntityManager($container->get(Connection::class), $configuration);
    });
    $container->set(TransactionalUnitOfWork::class, static function (Container $container): TransactionalUnitOfWork {
        return new DoctrineTransactionalUnitOfWork($container->get(EntityManagerInterface::class));
    });
};
