<?php

declare(strict_types=1);

namespace App\Adapter\Authorization;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Authorization\ManagedPolicyCatalog;
use Closure;
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
     *
     * @param string|Closure(string): (string|null|false) $source Directory or reader keyed by catalog filename
     */
    public function __construct(private string|Closure $source = __DIR__.'/../../../database/seeds')
    {
    }

    /**
     * Loads and validates both catalogs before returning any desired policy
     */
    public function load(): ManagedPolicy
    {
        return InstallationManagedPolicy::create(
            $this->read('permissions.json'),
            $this->read('roles.json')
        );
    }

    /**
     * Reads a required catalog from the installed directory
     */
    private static function readArtifact(string $path): string|null|false
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        return file_get_contents($path);
    }

    /**
     * Reads a required JSON list without an empty-policy fallback
     *
     * @return list<mixed>
     */
    private function read(string $filename): array
    {
        $source = $this->source;
        $contents = $source instanceof Closure ? $source($filename) : self::readArtifact($source.'/'.$filename);
        if ($contents === null) {
            throw new LogicException('Required installation catalog is unavailable.');
        }
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
