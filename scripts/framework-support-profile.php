<?php

declare(strict_types=1);

const FRAMEWORK_SUPPORT_CANDIDATE_PACKAGE = 'johnnickell/fight-common';
const FRAMEWORK_SUPPORT_ACCESS_CONTROL_PACKAGE = 'johnnickell/fight-access-control';

/**
 * Composer is the declaration of record for the untagged Fight Common candidate.
 * The selected-package matrix is intentionally explicit so lane verification can
 * reject profile expansion instead of merely checking a partial denylist.
 *
 * @return array{
 *     candidate: array{package: string, constraint: string, lock_version: string, version: string, reference: string},
 *     selected_direct_runtime_packages: list<string>,
 *     other_framework_packages: list<string>,
 *     unselected_optional_runtime_packages: list<string>
 * }
 */
function frameworkSupportProfile(string $projectRoot): array
{
    $manifest = frameworkSupportComposerManifest($projectRoot.'/composer.json');
    $require = $manifest['require'] ?? null;
    if (!is_array($require) || !is_string($require[FRAMEWORK_SUPPORT_CANDIDATE_PACKAGE] ?? null)) {
        throw new RuntimeException('composer.json must require the Fight Common candidate.');
    }

    $constraint = $require[FRAMEWORK_SUPPORT_CANDIDATE_PACKAGE];
    if (preg_match('/^(dev-[^#\s]+)#([a-f0-9]{40})\s+as\s+([^\s]+)$/', $constraint, $matches) !== 1) {
        throw new RuntimeException('Fight Common must use an immutable Composer commit reference with an explicit alias.');
    }

    $selectedDirectRuntimePackages = array_keys($require);
    $phpIndex = array_search('php', $selectedDirectRuntimePackages, true);
    if ($phpIndex !== false) {
        unset($selectedDirectRuntimePackages[$phpIndex]);
    }
    sort($selectedDirectRuntimePackages);

    $expectedDirectRuntimePackages = [
        'doctrine/dbal', 'doctrine/orm', 'guzzlehttp/guzzle',
        FRAMEWORK_SUPPORT_ACCESS_CONTROL_PACKAGE, FRAMEWORK_SUPPORT_CANDIDATE_PACKAGE,
        'lcobucci/jwt', 'league/flysystem', 'league/flysystem-local', 'monolog/monolog',
        'psr/cache', 'psr/container', 'psr/http-client', 'psr/http-factory', 'psr/http-message',
        'psr/http-server-handler', 'psr/log', 'psr/simple-cache', 'slim/psr7', 'slim/slim',
        'symfony/cache', 'symfony/filesystem', 'symfony/mailer', 'symfony/mercure', 'symfony/process',
        'symfony/validator', 'twig/twig',
    ];
    sort($expectedDirectRuntimePackages);
    if ($selectedDirectRuntimePackages !== $expectedDirectRuntimePackages) {
        throw new RuntimeException('composer.json direct runtime requirements do not match the selected Slim profile.');
    }

    return [
        'candidate' => [
            'package' => FRAMEWORK_SUPPORT_CANDIDATE_PACKAGE,
            'constraint' => $constraint,
            'lock_version' => $matches[1],
            'reference' => $matches[2],
            'version' => $matches[3],
        ],
        'selected_direct_runtime_packages' => $selectedDirectRuntimePackages,
        'other_framework_packages' => [
            'laravel/framework', 'symfony/framework-bundle', 'yiisoft/app', 'codeigniter4/framework',
        ],
        'unselected_optional_runtime_packages' => [
            'codeigniter4/queue', 'dragonmantank/cron-expression', 'league/flysystem-sftp',
            'php-di/php-di', 'php-di/slim-bridge', 'phpseclib/phpseclib',
            'slim/csrf', 'slim/twig-view', 'symfony/dependency-injection', 'symfony/finder',
            'symfony/http-kernel', 'symfony/messenger', 'symfony/routing', 'symfony/scheduler', 'twilio/sdk',
            'yiisoft/config', 'yiisoft/db', 'yiisoft/db-sqlite', 'yiisoft/di', 'yiisoft/files',
            'yiisoft/mailer', 'yiisoft/router', 'yiisoft/router-fastroute', 'yiisoft/view',
        ],
    ];
}

/** @return array<string, mixed> */
function frameworkSupportComposerManifest(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Composer manifest is missing: %s', $path));
    }

    $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) {
        throw new RuntimeException(sprintf('Composer manifest is invalid: %s', $path));
    }

    return $manifest;
}

/** @return array<string, array<string, mixed>> */
function frameworkSupportLockedRuntimePackages(string $lockPath): array
{
    $lock = json_decode((string) file_get_contents($lockPath), true, flags: JSON_THROW_ON_ERROR);
    $packages = [];
    foreach ($lock['packages'] ?? [] as $package) {
        if (is_array($package) && is_string($package['name'] ?? null)) {
            $packages[$package['name']] = $package;
        }
    }

    return $packages;
}

/** @return array<string, array<string, mixed>> */
function frameworkSupportInstalledPackages(string $installedPath): array
{
    $installed = json_decode((string) file_get_contents($installedPath), true, flags: JSON_THROW_ON_ERROR);
    $packages = $installed['packages'] ?? $installed;
    if (!is_array($packages)) {
        throw new RuntimeException(sprintf('Composer installed manifest is invalid: %s', $installedPath));
    }

    $byName = [];
    foreach ($packages as $package) {
        if (is_array($package) && is_string($package['name'] ?? null)) {
            $byName[$package['name']] = $package;
        }
    }

    return $byName;
}

/** @param array<string, mixed> $package */
function frameworkSupportPackageReference(array $package): ?string
{
    $reference = $package['source']['reference'] ?? $package['dist']['reference'] ?? null;

    return is_string($reference) ? $reference : null;
}

/** @param array<string, mixed> $profile */
function frameworkSupportAssertLane(string $laneRoot, array $profile): void
{
    if (frameworkSupportProfile($laneRoot) !== $profile) {
        throw new RuntimeException('Dependency lane Composer manifest does not match the selected Slim profile.');
    }

    frameworkSupportAssertRuntimePackageMatrix(
        frameworkSupportLockedRuntimePackages($laneRoot.'/composer.lock'),
        $profile,
        'composer.lock',
    );
    frameworkSupportAssertRuntimePackageMatrix(
        frameworkSupportInstalledPackages($laneRoot.'/vendor/composer/installed.json'),
        $profile,
        'vendor/composer/installed.json',
    );
}

/**
 * @param array<string, array<string, mixed>> $packages
 * @param array{candidate: array{package: string, constraint: string, lock_version: string, version: string, reference: string}, selected_direct_runtime_packages: list<string>, other_framework_packages: list<string>, unselected_optional_runtime_packages: list<string>} $profile
 */
function frameworkSupportAssertRuntimePackageMatrix(array $packages, array $profile, string $manifest): void
{
    $candidate = $profile['candidate'];
    $candidatePackage = $packages[$candidate['package']] ?? null;
    if (!is_array($candidatePackage)
        || ($candidatePackage['version'] ?? null) !== $candidate['lock_version']
        || frameworkSupportPackageReference($candidatePackage) !== $candidate['reference']) {
        throw new RuntimeException(sprintf('Fight Common candidate identity is not exact in %s.', $manifest));
    }

    foreach ($profile['selected_direct_runtime_packages'] as $package) {
        if (!isset($packages[$package])) {
            throw new RuntimeException(sprintf('Selected runtime package is missing from %s: %s', $manifest, $package));
        }
    }

    foreach (array_merge($profile['other_framework_packages'], $profile['unselected_optional_runtime_packages']) as $package) {
        if (isset($packages[$package])) {
            throw new RuntimeException(sprintf('Unselected package is present in %s: %s', $manifest, $package));
        }
    }
}

function frameworkSupportComposerCandidateWarning(string $projectRoot): string
{
    $candidate = frameworkSupportProfile($projectRoot)['candidate'];

    return sprintf('- The package "%s" is pointing to a commit-ref, this is bad practice and can cause unforeseen issues.', $candidate['package']);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $projectRoot = dirname(__DIR__);
    $command = $argv[1] ?? '';

    try {
        $profile = frameworkSupportProfile($projectRoot);
        switch ($command) {
            case '--candidate-reference':
                fwrite(STDOUT, $profile['candidate']['reference'].PHP_EOL);
                break;
            case '--composer-warning':
                fwrite(STDOUT, frameworkSupportComposerCandidateWarning($projectRoot).PHP_EOL);
                break;
            case '--assert-lane':
                $laneRoot = $argv[2] ?? '';
                if ($laneRoot === '' || !is_dir($laneRoot)) {
                    throw new RuntimeException('Usage: framework-support-profile.php --assert-lane <lane-root>');
                }
                frameworkSupportAssertLane($laneRoot, $profile);
                break;
            default:
                throw new RuntimeException('Usage: framework-support-profile.php --candidate-reference|--composer-warning|--assert-lane <lane-root>');
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage().PHP_EOL);
        exit(1);
    }
}
