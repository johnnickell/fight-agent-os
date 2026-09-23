<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

try {
    $projectRoot = dirname(__DIR__);
    $installedPath = sprintf('%s/vendor/composer/installed.php', $projectRoot);
    $autoloadPath = sprintf('%s/vendor/autoload.php', $projectRoot);
    $sourceRoot = realpath(sprintf('%s/src', $projectRoot));

    if (!is_file($installedPath) || !is_file($autoloadPath) || $sourceRoot === false) {
        throw new RuntimeException('Production dependencies, autoload, and owned source must be present.');
    }

    $installed = require $installedPath;
    foreach (['johnnickell/fight-common', 'johnnickell/fight-access-control'] as $package) {
        if (!isset($installed['versions'][$package])) {
            throw new RuntimeException("Required production dependency {$package} is not installed.");
        }
    }

    if (isset($installed['versions']['phpunit/phpunit']) || is_file(sprintf('%s/vendor/bin/phpunit', $projectRoot))) {
        throw new RuntimeException('Development-only package phpunit/phpunit is installed in production.');
    }

    $loader = require $autoloadPath;
    if (!$loader instanceof ClassLoader) {
        throw new RuntimeException('Composer production autoload did not return a class loader.');
    }

    $appPaths = $loader->getPrefixesPsr4()['App\\'] ?? [];
    $appSourceIsMapped = false;
    foreach ($appPaths as $appPath) {
        if (realpath($appPath) === $sourceRoot) {
            $appSourceIsMapped = true;
            break;
        }
    }
    if (!$appSourceIsMapped) {
        throw new RuntimeException('Production autoload does not map the App namespace to owned source.');
    }

    $readNamespaces = static function (string $path): array {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to inspect owned source file {$path}.");
        }

        $tokens = token_get_all($contents);
        $namespaces = [];
        $tokenCount = count($tokens);
        for ($index = 0; $index < $tokenCount; ++$index) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_NAMESPACE) {
                continue;
            }

            $namespace = '';
            for (++$index; $index < $tokenCount; ++$index) {
                $token = $tokens[$index];
                if ($token === ';' || $token === '{') {
                    break;
                }
                if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $namespace .= $token[1];
                }
            }

            $namespaces[] = ltrim($namespace, '\\');
        }

        return $namespaces;
    };

    $forbiddenNamespaces = ['fight\\common', 'fight\\accesscontrol'];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($files as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $relativePath = str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            substr($file->getPathname(), strlen($sourceRoot) + 1)
        );
        if (preg_match('#(^|/)Fight/(Common|AccessControl)(/|$)#i', $relativePath) === 1) {
            throw new RuntimeException("Copied Fight package source path is forbidden: src/{$relativePath}.");
        }

        foreach ($readNamespaces($file->getPathname()) as $namespace) {
            $normalizedNamespace = strtolower($namespace);
            foreach ($forbiddenNamespaces as $forbiddenNamespace) {
                if (
                    $normalizedNamespace === $forbiddenNamespace
                    || str_starts_with($normalizedNamespace, "{$forbiddenNamespace}\\")
                ) {
                    throw new RuntimeException(
                        "Copied package namespace {$namespace} is forbidden in src/{$relativePath}."
                    );
                }
            }
        }
    }

    fwrite(STDOUT, "Production dependency and source contract passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Production dependency and source contract failed: {$exception->getMessage()}\n");
    exit(1);
}
