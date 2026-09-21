<?php

declare(strict_types=1);

namespace Tests\Fixture\FrameworkSupport;

final class DependencyLaneFixture
{
    private string $root;

    private function __construct()
    {
        $this->root = sys_get_temp_dir().'/fight-slim-dependency-lane-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/vendor/composer', 0700, true);

        $projectRoot = dirname(__DIR__, 3);
        copy($projectRoot.'/composer.json', $this->root.'/composer.json');
        copy($projectRoot.'/composer.lock', $this->root.'/composer.lock');
        copy($projectRoot.'/vendor/composer/installed.json', $this->root.'/vendor/composer/installed.json');
    }

    public static function create(): self
    {
        return new self();
    }

    public function root(): string
    {
        return $this->root;
    }

    public function injectLockedPackage(string $name): void
    {
        $this->mutateJson($this->root.'/composer.lock', static function (array $manifest) use ($name): array {
            $manifest['packages'][] = ['name' => $name, 'version' => 'test-injected'];

            return $manifest;
        });
    }

    public function injectInstalledPackage(string $name): void
    {
        $this->mutateJson($this->root.'/vendor/composer/installed.json', static function (array $manifest) use ($name): array {
            $manifest['packages'][] = ['name' => $name, 'version' => 'test-injected'];

            return $manifest;
        });
    }

    public function removeInstalledPackage(string $name): void
    {
        $this->mutateJson($this->root.'/vendor/composer/installed.json', static function (array $manifest) use ($name): array {
            $manifest['packages'] = array_values(array_filter(
                $manifest['packages'],
                static fn (array $package): bool => ($package['name'] ?? null) !== $name,
            ));

            return $manifest;
        });
    }

    public function remove(): void
    {
        unlink($this->root.'/vendor/composer/installed.json');
        rmdir($this->root.'/vendor/composer');
        rmdir($this->root.'/vendor');
        unlink($this->root.'/composer.lock');
        unlink($this->root.'/composer.json');
        rmdir($this->root);
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $mutator */
    private function mutateJson(string $path, callable $mutator): void
    {
        $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $manifest = $mutator($manifest);
        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL);
    }
}
