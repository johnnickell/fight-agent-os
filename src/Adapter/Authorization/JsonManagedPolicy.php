<?php

declare(strict_types=1);

namespace App\Adapter\Authorization;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Authorization\ManagedPolicyCatalog;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy;
use LogicException;

/**
 * Class JsonManagedPolicy
 *
 * Loads the complete installation catalogs without applying authority
 */
final readonly class JsonManagedPolicy implements ManagedPolicyCatalog
{
    /**
     * Constructs JsonManagedPolicy
     */
    public function __construct(private string $directory = __DIR__.'/../../../database/seeds')
    {
    }

    /**
     * Loads and validates both catalogs before returning any desired policy
     */
    public function load(): ManagedPolicy
    {
        return InstallationManagedPolicy::create(
            self::read($this->directory.'/permissions.json'),
            self::read($this->directory.'/roles.json')
        );
    }

    /**
     * Reads a required JSON list without an empty-policy fallback
     *
     * @return list<mixed>
     */
    private static function read(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new LogicException('Required installation catalog is unavailable.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new LogicException('Required installation catalog could not be read.');
        }
        $data = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !array_is_list($data) || $data === []) {
            throw new LogicException('Required installation catalog must be a nonempty list.');
        }

        return $data;
    }
}
