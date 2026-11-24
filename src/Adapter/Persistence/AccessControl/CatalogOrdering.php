<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\AccessControl;

use Doctrine\DBAL\Query\QueryBuilder;
use Fight\Common\Domain\Repository\Pagination;
use InvalidArgumentException;

/**
 * Class CatalogOrdering
 *
 * Applies supported catalog orderings before pagination
 */
final class CatalogOrdering
{
    /**
     * Applies the requested ordering with a stable identity tie-break
     */
    public static function apply(QueryBuilder $query, Pagination $pagination): QueryBuilder
    {
        $orderings = $pagination->orderings();
        if ($orderings === []) {
            return $query->orderBy('created_at')->addOrderBy('id');
        }
        foreach ($orderings as $field => $direction) {
            $column = match ($field) {
                'id' => 'id',
                'created_at' => 'created_at',
                default => throw new InvalidArgumentException('Unsupported catalog ordering.'),
            };
            $query->addOrderBy($column, $direction === Pagination::DESC ? 'DESC' : 'ASC');
        }
        if (!isset($orderings['id'])) {
            // Keep the database's default ascending order for the tie-break.
            $query->addOrderBy('id');
        }

        return $query;
    }
}
