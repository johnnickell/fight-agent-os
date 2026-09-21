<?php

declare(strict_types=1);

use Fight\Common\Adapter\FileStorage\FlysystemStorage;
use Fight\Common\Adapter\FileTransfer\Null\NullFileTransport;
use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem as FightSymfonyFilesystem;
use Fight\Common\Application\FileStorage\FileStorage;
use Fight\Common\Application\FileStorage\StorageService;
use Fight\Common\Application\FileTransfer\FileTransferService;
use Fight\Common\Application\FileTransfer\Transport\FileTransport;
use Fight\Common\Application\Filesystem\Filesystem as FightFilesystem;
use Fight\Common\Application\Service\Container;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

return static function (Container $container): void {
    $container->set(FilesystemOperator::class, static function (Container $container): FilesystemOperator {
        return new Filesystem(new LocalFilesystemAdapter($container['app.storage_dir']));
    });
    $container->set(FileStorage::class, static function (Container $container): FileStorage {
        return new FlysystemStorage($container->get(FilesystemOperator::class));
    });
    $container->set(FileTransport::class, static function (): FileTransport {
        return new NullFileTransport();
    });
    $container->set(SymfonyFilesystem::class, static function (): SymfonyFilesystem {
        return new SymfonyFilesystem();
    });
    $container->set(FightFilesystem::class, static function (Container $container): FightFilesystem {
        return new FightSymfonyFilesystem($container->get(SymfonyFilesystem::class));
    });
    $container->set(StorageService::class, static function (Container $container): StorageService {
        $storage = new StorageService();
        $storage->addStorage('local', $container->get(FileStorage::class));

        return $storage;
    });
    $container->set(FileTransferService::class, static function (Container $container): FileTransferService {
        $transfers = new FileTransferService();
        $transfers->addTransport('null', $container->get(FileTransport::class));

        return $transfers;
    });
};
