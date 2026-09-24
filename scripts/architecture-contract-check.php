<?php

declare(strict_types=1);

try {
    $projectRoot = dirname(__DIR__);
    $arguments = array_slice($argv, 1);
    $roots = $arguments === [] ? [sprintf('%s/src', $projectRoot)] : $arguments;
    $files = [];
    foreach ($roots as $root) {
        $path = str_starts_with($root, '/') ? $root : sprintf('%s/%s', $projectRoot, $root);
        if (is_link($path)) {
            throw new RuntimeException("Architecture source root must not be a symlink: {$root}.");
        }
        if (is_file($path)) {
            $files[] = $path;
            continue;
        }
        if (!is_dir($path)) {
            throw new RuntimeException("Architecture source path does not exist: {$root}.");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException("Symlinks are forbidden in architecture source: {$file->getPathname()}.");
            }
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);
    $violations = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to inspect architecture source file {$file}.");
        }

        $tokens = token_get_all($contents);
        $namespace = '';
        $declarations = [];
        $imports = [];
        $tokenCount = count($tokens);
        for ($index = 0; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';
                for (++$index; $index < $tokenCount; ++$index) {
                    $part = $tokens[$index];
                    if ($part === ';' || $part === '{') {
                        break;
                    }
                    if (is_array($part) && in_array($part[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $namespace .= $part[1];
                    }
                }
                $namespace = ltrim($namespace, '\\');
                continue;
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $previous = $index - 1;
                while ($previous >= 0 && is_array($tokens[$previous]) && in_array(
                    $tokens[$previous][0],
                    [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT],
                    true
                )) {
                    --$previous;
                }
                if ($token[0] === T_CLASS && $previous >= 0 && is_array($tokens[$previous]) && $tokens[$previous][0] === T_NEW) {
                    continue;
                }
                for (++$index; $index < $tokenCount; ++$index) {
                    $part = $tokens[$index];
                    if (is_array($part) && $part[0] === T_STRING) {
                        $declarations[] = $part[1];
                        break;
                    }
                }
                continue;
            }

            if ($token[0] === T_USE) {
                $import = '';
                for (++$index; $index < $tokenCount; ++$index) {
                    $part = $tokens[$index];
                    if ($part === ';') {
                        break;
                    }
                    $import .= is_array($part) ? $part[1] : $part;
                }
                foreach (explode(',', $import) as $candidate) {
                    $candidate = trim($candidate);
                    if (preg_match('/^Fight\\\\(?:Common|AccessControl)\\\\/i', $candidate) === 1) {
                        $imports[] = $candidate;
                    }
                }
            }
        }

        $relativePath = str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            str_starts_with($file, $projectRoot . DIRECTORY_SEPARATOR)
                ? substr($file, strlen($projectRoot) + 1)
                : $file
        );
        $normalizedNamespace = strtolower($namespace);
        if (
            $normalizedNamespace === 'fight\\common'
            || str_starts_with($normalizedNamespace, 'fight\\common\\')
            || $normalizedNamespace === 'fight\\accesscontrol'
            || str_starts_with($normalizedNamespace, 'fight\\accesscontrol\\')
        ) {
            $violations[] = "{$relativePath}: copied package namespace {$namespace} is forbidden.";
        }

        $isBusinessCode = preg_match('/^App\\\\(?:Domain|Application)(?:\\\\|$)/', $namespace) === 1;
        if ($isBusinessCode && preg_match(
            '/(?:Fight\\\\Common\\\\Application\\\\Service\\\\Container|Psr\\\\Container\\\\ContainerInterface|\\$container\s*->\s*(?:get|has)\s*\()/i',
            $contents
        ) === 1) {
            $violations[] = "{$relativePath}: Domain/Application code must not locate services through a container.";
        }

        if ($isBusinessCode) {
            $packageTypeNames = [];
            foreach ($imports as $import) {
                $withoutAlias = preg_split('/\s+as\s+/i', $import, 2)[0];
                $segments = explode('\\\\', $withoutAlias);
                $packageTypeNames[] = strtolower((string) end($segments));
            }
            if (preg_match_all(
                '/Fight\\\\(?:Common|AccessControl)\\\\(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*([A-Za-z_][A-Za-z0-9_]*)/i',
                $contents,
                $matches
            ) > 0) {
                $packageTypeNames = [...$packageTypeNames, ...array_map('strtolower', $matches[1])];
            }
            foreach ($declarations as $declaration) {
                if (in_array(strtolower($declaration), $packageTypeNames, true)) {
                    $violations[] = "{$relativePath}: owned {$declaration} duplicates a referenced Fight package type name.";
                }
            }
        }
    }

    if ($violations !== []) {
        fwrite(STDERR, "Architecture source contract failed:\n- " . implode("\n- ", $violations) . "\n");
        exit(1);
    }

    fwrite(STDOUT, sprintf("Architecture source contract passed: %d PHP files inspected.\n", count($files)));
} catch (Throwable $exception) {
    fwrite(STDERR, "Architecture source contract failed: {$exception->getMessage()}\n");
    exit(1);
}
