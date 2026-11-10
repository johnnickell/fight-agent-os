<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Adapter\Authorization\JsonManagedPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Proves required catalog input fails closed without a partial desired policy
 */
final class JsonManagedPolicyTest extends TestCase
{
    /**
     * Rejects absent and malformed input independently in either required catalog
     */
    #[DataProvider('invalidInputs')]
    public function testRequiredInput(string $filename, ?string $contents): void
    {
        $source = dirname(__DIR__, 3).'/database/seeds';
        $directory = dirname(__DIR__, 3).'/var/tests/catalog-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        try {
            foreach (['permissions.json', 'roles.json'] as $file) {
                copy($source.'/'.$file, $directory.'/'.$file);
            }
            self::assertSame(
                (new JsonManagedPolicy())->load()->toArray(),
                (new JsonManagedPolicy($directory))->load()->toArray()
            );
            if ($contents === null) {
                unlink($directory.'/'.$filename);
            } else {
                file_put_contents($directory.'/'.$filename, $contents);
            }
            $this->expectException(Throwable::class);
            (new JsonManagedPolicy($directory))->load();
        } finally {
            foreach (['permissions.json', 'roles.json'] as $file) {
                if (is_file($directory.'/'.$file)) {
                    unlink($directory.'/'.$file);
                }
            }
            rmdir($directory);
        }
    }

    /**
     * Supplies invalid serialized inputs without changing the production catalog
     *
     * @return iterable<string, array{string, ?string}>
     */
    public static function invalidInputs(): iterable
    {
        foreach (['permissions.json', 'roles.json'] as $filename) {
            foreach ([null, '', '{private-marker', '{}', '[]', 'null', '1', '[1]'] as $index => $contents) {
                yield $filename.'-'.$index => [$filename, $contents];
            }
        }
    }
}
