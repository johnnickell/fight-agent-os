<?php

declare(strict_types=1);

namespace Tests\Unit\CredentialDelivery;

use App\Adapter\Persistence\ExpiredCredentialDeliveryRecords;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class ExpiryDiscoveryTest
 *
 * Proves unsafe discovery requests cannot reach persistence
 */
final class ExpiryDiscoveryTest extends TestCase
{
    /**
     * Rejects unbounded or unsupported work before reading a database
     */
    #[DataProvider('invalidRequests')]
    public function testRejectsInvalidDiscovery(string $purpose, int $limit, string $message): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchAllAssociative');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        ExpiredCredentialDeliveryRecords::find(
            $connection,
            $purpose,
            new DateTimeImmutable('2026-10-01T12:00:00Z'),
            $limit
        );
    }

    /**
     * Supplies invalid requests at the persistence capability boundary
     *
     * @return iterable<array{string, int, string}>
     */
    public static function invalidRequests(): iterable
    {
        yield ['activation', 0, 'Expiry page size must be between 1 and 100.'];
        yield ['password_reset', -1, 'Expiry page size must be between 1 and 100.'];
        yield ['email_change', 101, 'Expiry page size must be between 1 and 100.'];
        yield ['unknown', 1, 'Unsupported expiry purpose.'];
    }
}
