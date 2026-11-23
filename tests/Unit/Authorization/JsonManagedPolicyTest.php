<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Adapter\Authorization\JsonManagedPolicy;
use JsonException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\InstallationCatalogFixture;
use Throwable;

/**
 * Checks catalog decoding and validation without reading installed files
 */
final class JsonManagedPolicyTest extends TestCase
{
    /**
     * Loads both catalogs and retains the supplied permission and role identities
     */
    public function testLoadsCompletePolicy(): void
    {
        [$permissions, $roles] = InstallationCatalogFixture::catalogs();
        $policy = $this->catalog()->load();

        self::assertSame(array_column($permissions, 'id'), array_map(
            static fn ($permission): string => $permission->getId()->toString(),
            $policy->getPermissions()
        ));
        self::assertSame(array_column($roles, 'id'), array_map(
            static fn ($role): string => $role->getId()->toString(),
            $policy->getRoles()
        ));
    }

    /**
     * Rejects missing or invalid input independently for either catalog
     *
     * @phpstan-param class-string<Throwable> $exception
     */
    #[DataProvider('invalidInputs')]
    public function testRejectsRequiredInput(
        string $filename,
        string|null|false $contents,
        string $exception,
        string $message
    ): void {
        $this->expectException($exception);
        $this->expectExceptionMessage($message);
        $this->catalog([$filename => $contents])->load();
    }

    /**
     * Supplies the reader and decoding failures for both catalog names
     *
     * @return iterable<string, array{string, string|null|false, class-string<Throwable>, string}>
     */
    public static function invalidInputs(): iterable
    {
        foreach (['permissions.json', 'roles.json'] as $filename) {
            yield $filename.' missing' => [
                $filename, null, LogicException::class, 'Required installation catalog is unavailable.'
            ];
            yield $filename.' read failure' => [
                $filename, false, LogicException::class, 'Required installation catalog could not be read.'
            ];
            yield $filename.' malformed' => [$filename, '{invalid', JsonException::class, 'Syntax error'];
            foreach (['{}', '[]', 'null', '1'] as $contents) {
                yield $filename.' '.$contents => [
                    $filename,
                    $contents,
                    LogicException::class,
                    'Required installation catalog must be a nonempty list.'
                ];
            }
        }
    }

    /**
     * Supplies both catalog responses from memory
     *
     * @param array<string, string|null|false> $overrides
     */
    private function catalog(array $overrides = []): JsonManagedPolicy
    {
        [$permissions, $roles] = InstallationCatalogFixture::catalogs();
        $contents = $overrides + [
            'permissions.json' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'roles.json'       => json_encode($roles, JSON_THROW_ON_ERROR)
        ];

        return new JsonManagedPolicy(static fn (string $name): string|null|false => $contents[$name]);
    }
}
