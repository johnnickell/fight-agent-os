<?php

declare(strict_types=1);

namespace App\Adapter\Http\Documentation;

use RuntimeException;
use stdClass;
use Throwable;

/**
 * Class PrivateOpenApi
 *
 * Reads the bounded private export without runtime generation or alternate resources
 */
final readonly class PrivateOpenApi
{
    /**
     * Constructs PrivateOpenApi
     */
    public function __construct(private string $path)
    {
    }

    /**
     * Returns the complete artifact or a non-sensitive deployment failure
     */
    public function read(): string
    {
        try {
            if (realpath($this->path) !== $this->path || !is_file($this->path)) {
                throw new RuntimeException();
            }
            $size = filesize($this->path);
            if ($size === false || $size < 2 || $size > 2097152) {
                throw new RuntimeException();
            }
            $source = file_get_contents($this->path);
            if ($source === false || strlen($source) > 2097152) {
                throw new RuntimeException();
            }
            $document = json_decode($source, false, 128, JSON_THROW_ON_ERROR);
            if (
                !$document instanceof stdClass || ($document->openapi ?? null) !== '3.0.3'
                || !($document->paths ?? null) instanceof stdClass
                || !($document->info ?? null) instanceof stdClass
                || !is_string($document->info->title ?? null)
                || !is_string($document->info->version ?? null)
                || !is_string($document->{'x-source-sha256'} ?? null)
                || preg_match('/\A[a-f0-9]{64}\z/D', $document->{'x-source-sha256'}) !== 1
            ) {
                throw new RuntimeException();
            }
            self::assertLocal($document);
            foreach (get_object_vars($document->paths) as $path => $operation) {
                if (!str_starts_with($path, '/api/v1/') || !$operation instanceof stdClass) {
                    throw new RuntimeException();
                }
            }

            return $source;
        } catch (Throwable) {
            throw new RuntimeException('Private API documentation is unavailable.');
        }
    }

    /**
     * Rejects remote references and alternate servers at every nesting level
     */
    private static function assertLocal(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                self::assertLocal($child);
            }
        }
        if (!$value instanceof stdClass) {
            return;
        }
        foreach (get_object_vars($value) as $key => $child) {
            if ($key === '$ref' && (!is_string($child) || !str_starts_with($child, '#/'))) {
                throw new RuntimeException();
            }
            if ($key === 'servers') {
                if (!is_array($child)) {
                    throw new RuntimeException();
                }
                foreach ($child as $server) {
                    if (!$server instanceof stdClass || ($server->url ?? null) !== '/') {
                        throw new RuntimeException();
                    }
                }
            }
            self::assertLocal($child);
        }
    }
}
