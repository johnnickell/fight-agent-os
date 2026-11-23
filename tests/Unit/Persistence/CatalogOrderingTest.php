<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\CatalogOrdering;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Fight\Common\Domain\Repository\Pagination;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class CatalogOrderingTest
 */
final class CatalogOrderingTest extends TestCase
{
    /**
     * Applies order before slicing with ID as the stable tie-break
     *
     * @param array<string, string> $orderings
     */
    #[DataProvider('validOrderings')]
    public function testStableOrder(array $orderings, string $expected): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $query = new QueryBuilder($connection);
        $query->select('id')->from('users');
        CatalogOrdering::apply($query, Pagination::strict(1, 25, $orderings));
        self::assertSame('SELECT id FROM users ORDER BY '.$expected, $query->getSQL());
    }

    /**
     * Supplies each supported ordering and the default
     *
     * @return iterable<array{array<string, string>, string}>
     */
    public static function validOrderings(): iterable
    {
        yield [[], 'created_at, id'];
        yield [['id' => 'desc'], 'id DESC'];
        yield [['created_at' => 'desc'], 'created_at DESC, id'];
        yield [['created_at' => 'asc'], 'created_at ASC, id'];
    }

    /**
     * Rejects fields outside the catalog contract even for an internal caller
     */
    public function testUnsupportedField(): void
    {
        $query = new QueryBuilder($this->createMock(Connection::class));
        $this->expectException(InvalidArgumentException::class);
        CatalogOrdering::apply($query, Pagination::strict(1, 25, ['name' => 'asc']));
    }
}
