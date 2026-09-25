<?php

declare(strict_types=1);

namespace App\Adapter\Persistence;

use RuntimeException;

final class PersistenceConflict extends RuntimeException
{
}
