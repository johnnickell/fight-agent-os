<?php

declare(strict_types=1);

namespace Tests\Integration;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Cache\PsrCache;
use Fight\Common\Application\FileStorage\FileStorage;
use Fight\Common\Application\FileTransfer\FileTransferService;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;

final class PersistenceAndStorageJourneyTest extends TestCase
{
    public function test_booted_cache_contracts_store_read_and_clear_values(): void
    {
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();
        $pool = $container->get(CacheItemPoolInterface::class);
        $simple = $container->get(CacheInterface::class);
        $key = 'capability-journey-cache';

        $simple->set($key, 'psr16-ready');
        self::assertSame('psr16-ready', $simple->get($key));
        $item = $pool->getItem($key);
        $item->set('psr6-ready');
        $pool->save($item);
        self::assertSame('psr6-ready', $pool->getItem($key)->get());

        $loads = 0;
        self::assertSame('fight-cache-ready', $container->get(PsrCache::class)->read('fight-'.$key, static function () use (&$loads): string {
            $loads++;

            return 'fight-cache-ready';
        }, 60));
        self::assertSame('fight-cache-ready', $container->get(PsrCache::class)->read('fight-'.$key, static function () use (&$loads): string {
            $loads++;

            return 'unexpected';
        }, 60));
        self::assertSame(1, $loads);

        $pool->clear();
        self::assertFalse($pool->getItem($key)->isHit());
    }

    public function test_booted_dbal_unit_of_work_commits_and_rolls_back_transactions(): void
    {
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();
        $connection = $container->get(Connection::class);
        $table = 'capability_journey_transactions';
        $connection->executeStatement(sprintf('CREATE TABLE %s (value TEXT NOT NULL)', $table));

        try {
            $unitOfWork = $container->get(TransactionalUnitOfWork::class);
            $unitOfWork->commitTransactional(static function () use ($connection, $table): void {
                $connection->insert($table, ['value' => 'committed']);
            });

            try {
                $unitOfWork->commitTransactional(static function () use ($connection, $table): void {
                    $connection->insert($table, ['value' => 'rolled-back']);
                    throw new \RuntimeException('expected rollback');
                });
                self::fail('The transaction should have propagated its exception.');
            } catch (\RuntimeException $exception) {
                self::assertSame('expected rollback', $exception->getMessage());
            }

            self::assertSame(['committed'], $connection->fetchFirstColumn(sprintf('SELECT value FROM %s', $table)));
        } finally {
            $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $table));
        }
    }

    public function test_booted_local_storage_filesystem_and_null_transfer_have_safe_behavior_and_clean_up(): void
    {
        $root = dirname(__DIR__, 2);
        $container = (require $root.'/bootstrap/app.php')->getContainer();
        $storage = $container->get(FileStorage::class);
        $filesystem = $container->get(Filesystem::class);
        $prefix = 'capability-journey-'.bin2hex(random_bytes(6));
        $storagePath = $prefix.'/stored.txt';
        $filesystemPath = $root.'/var/'.$prefix.'/filesystem.txt';

        try {
            $storage->putFile($storagePath, 'stored-ready');
            self::assertSame('stored-ready', $storage->getFileContents($storagePath));
            self::assertTrue($storage->hasFile($storagePath));
            self::assertSame(12, $storage->size($storagePath));

            $filesystem->put($filesystemPath, 'filesystem-ready');
            self::assertSame('filesystem-ready', $filesystem->get($filesystemPath));
            self::assertTrue($filesystem->exists($filesystemPath));

            $transfer = $container->get(FileTransferService::class)->getTransport('null');
            $transfer->sendFile('ignored.txt', 'safe');
            self::assertSame('', $transfer->retrieveFileContents('ignored.txt'));
        } finally {
            $filesystem->remove($root.'/var/storage/'.$prefix);
            $filesystem->remove($root.'/var/'.$prefix);
        }

        self::assertFalse($filesystem->exists($root.'/var/storage/'.$prefix));
        self::assertFalse($filesystem->exists($root.'/var/'.$prefix));
    }
}
