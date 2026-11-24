<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\XdebugDriver;
use SebastianBergmann\CodeCoverage\Filter;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$reports = $root.'/.runs/build/reports/backend';
$artifact = $reports.'/unit-coverage.php';
if (!is_file($artifact) || is_link($artifact)) {
    throw new RuntimeException('Missing local PHPUnit Unit coverage artifact.');
}
// Read only the trusted local PHP artifact written by the Unit invocation in backend_tests.php.
$coverage = require $artifact;
if (!$coverage instanceof CodeCoverage) {
    throw new RuntimeException('Invalid PHPUnit Unit coverage artifact.');
}
$hits = $coverage->getData(true)->lineCoverage();

// PHPUnit synthesizes line data with its parser only for files absent from the runtime report.
// Compile all owned files so the same Xdebug/PHPUnit line rules apply whether Unit loaded them or not.
$source = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src'));
$owned = [];
foreach ($source as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $owned[$file->getPathname()] = true;
    }
}
$configured = array_fill_keys($coverage->filter()->files(), true);
if ($owned === [] || array_diff_key($owned, $configured) !== [] || array_diff_key($configured, $owned) !== []) {
    throw new RuntimeException('Unit coverage does not match the owned PHP source inventory.');
}
if (ini_get('opcache.enable_cli') !== '1' || ini_get('opcache.optimization_level') !== '0') {
    throw new RuntimeException('Unit coverage inventory requires CLI OPcache with optimization disabled.');
}
$filter = new Filter();
$filter->includeFiles(array_keys($owned));
$inventory = new CodeCoverage(new XdebugDriver($filter), $filter);
$inventory->disableAnnotationsForIgnoringCode();
$inventory->start('source inventory');
foreach ($filter->files() as $file) {
    if (!opcache_compile_file($file)) {
        throw new RuntimeException('Cannot compile for Unit coverage inventory: '.$file);
    }
}
// Compiling does not run product behavior, and inventory data must never contribute hits.
$inventory->stop(append: false);
$data = $inventory->getData(true)->lineCoverage();
if (array_diff_key($owned, $data) !== [] || array_diff_key($data, $owned) !== []) {
    throw new RuntimeException('Xdebug did not inventory every owned PHP file.');
}
foreach ($hits as $file => $lines) {
    if (!isset($data[$file])) {
        throw new RuntimeException('Unexpected Unit coverage file: '.$file);
    }
    foreach ($lines as $line => $tests) {
        if ($tests !== null && !isset($data[$file][$line])) {
            throw new RuntimeException('Unit coverage disagrees with executable inventory: '.$file.':'.$line);
        }
    }
}

$fileExclusions = [
    'src/Adapter/Persistence/Guard/DatabaseTargetGuard.php' => 'Destructive test-tool safety, verified directly',
    'src/Adapter/Validation/PublicForms.php'                => 'Publication composition registry'
];
$openApiIo = 'Native private OpenAPI filesystem admission';
$catalogIo = 'Native public form catalog filesystem admission';
$installationIo = 'Native installation catalog filesystem admission';
$rootIo = 'Native approved-root filesystem and process permission probes, verified directly';
$runtimeLines = [
    'src/Adapter/Installation/NativeApprovedRootFilesystem.php' => [
        19 => ['clearstatcache(true);', $rootIo],
        21 => ['return is_dir($directory);', $rootIo],
        29 => ['return @file_get_contents($path, length: $length);', $rootIo],
        37 => ['$searchable = is_dir($directory) && is_executable($directory);', $rootIo],
        39 => ['return [', $rootIo],
        40 => ["'readable' => \$searchable && is_readable(\$directory),", $rootIo],
        41 => ["'writable' => \$searchable && \$writeGranted && is_writable(\$directory)", $rootIo],
        42 => ['];', $rootIo]
    ],
    'src/Adapter/Authorization/JsonManagedPolicy.php'           => [
        45 => ['if (!is_file($path) || !is_readable($path)) {', $installationIo],
        46 => ['return null;', $installationIo],
        49 => ['return file_get_contents($path);', $installationIo]
    ],
    'src/Adapter/Http/Documentation/PrivateOpenApi.php'         => [
        68 => ['if (realpath($path) !== $path || !is_file($path)) {', $openApiIo],
        69 => ['return false;', $openApiIo],
        71 => ['$size = filesize($path);', $openApiIo],
        72 => ['if ($size === false || $size < 2 || $size > 2097152) {', $openApiIo],
        73 => ['return false;', $openApiIo],
        76 => ['return file_get_contents($path);', $openApiIo]
    ],
    'src/Adapter/Validation/Catalog.php'                        => [
        140 => ['is_link($path) || realpath(dirname($path)) !== dirname($path)', $catalogIo],
        141 => ['|| !is_file($path) || filesize($path) > 262144', $catalogIo],
        143 => ['return false;', $catalogIo],
        146 => ['return file_get_contents($path);', $catalogIo]
    ],
    'src/Adapter/Security/Password/Argon2idPasswords.php'       => [
        30 => ['throw new RuntimeException();', 'Installed PHP lacks native Argon2id support'],
        38 => ['} catch (Throwable) {', 'Catch an installed-runtime failure during the Argon2id startup probe'],
        39 => ["throw new RuntimeException('Argon2id password support is unavailable.');",
            'Report a failed native Argon2id startup probe'],
        50 => ['} catch (Throwable) {', 'Catch native password_hash failure using fixed valid options'],
        51 => ["throw new RuntimeException('Password hashing failed.');", 'Report native password_hash failure']
    ]
];
$excludedMethods = [
    'Application/AccessControl/User/QueryHandler/ListDirectoryUsersHandler'                 => 'queryRegistration',
    'Application/Authorization/CommandHandler/AddUserRoleHandler'                           => 'commandRegistration',
    'Application/Authorization/CommandHandler/BootstrapSuperAdminHandler'                   => 'commandRegistration',
    'Application/Authorization/CommandHandler/InviteOrdinaryUserHandler'                    => 'commandRegistration',
    'Application/Security/Csrf/QueryHandler/GetCsrfProofHandler'                            => 'queryRegistration',
    'Application/Security/Session/QueryHandler/ListOwnedSessionsHandler'                    => 'queryRegistration',
    'Application/Workspace/Project/CommandHandler/RegisterProjectHandler'                   => 'commandRegistration',
    'Application/Workspace/Project/QueryHandler/GetProjectByIdHandler'                      => 'queryRegistration',
    'Application/Workspace/Project/QueryHandler/ListProjectsHandler'                        => 'queryRegistration',
    'Application/Workspace/Project/QueryHandler/PreviewProjectConflictsHandler'             => 'queryRegistration',
    'Application/Workspace/Workspace/CommandHandler/BootstrapWorkspaceHandler'              => 'commandRegistration',
    'Application/Workspace/Workspace/QueryHandler/FindInitialWorkspaceHandler'              => 'queryRegistration',
    'Application/Workspace/Workspace/QueryHandler/GetWorkspaceByIdHandler'                  => 'queryRegistration',
    'Adapter/Persistence/AccessControl/ActivationGrant/ActivationGrantRecords'              => '__construct',
    'Adapter/Persistence/AccessControl/ActivationGrant/ActivationGrantTransitions'          => '__construct',
    'Adapter/Persistence/AccessControl/CredentialDelivery/ExpiredCredentialDeliveryRecords' => '__construct',
    'Adapter/Persistence/AccessControl/EmailChangeGrant/EmailChangeGrantRecords'            => '__construct',
    'Adapter/Persistence/AccessControl/PasswordResetGrant/PasswordResetGrantRecords'        => '__construct',
    'Adapter/Persistence/PostgresAtomicOperation'                                           => '__construct',
    'Adapter/Persistence/PostgresUniqueConstraintRace'                                      => '__construct',
    'Adapter/Persistence/RefreshSessionRecords'                                             => '__construct'
];
$methodExclusions = [];
foreach ($excludedMethods as $class => $name) {
    $method = new ReflectionMethod('App\\'.str_replace('/', '\\', $class), $name);
    $path = 'src/'.$class.'.php';
    if ($method->getFileName() !== $root.'/'.$path || ($name === '__construct' && !$method->isPrivate())) {
        throw new RuntimeException('Invalid coverage exclusion: '.$path.'::'.$name);
    }
    $reason = 'Static bus registration metadata, not handler behavior';
    if ($name === '__construct') {
        $reason = 'Empty private constructor prevents instantiation of a static helper';
    }
    $methodExclusions[$path] = [$method->getStartLine(), $method->getEndLine(), $reason];
}

foreach ($runtimeLines as $path => $entries) {
    $contents = file($root.'/'.$path);
    foreach ($entries as $line => [$statement, $reason]) {
        if (trim($contents[$line - 1] ?? '') !== $statement || !isset($data[$root.'/'.$path][$line])) {
            throw new RuntimeException('Stale runtime coverage exclusion: '.$path.':'.$line);
        }
    }
}

ksort($data);
$required = $covered = 0;
$gaps = $exclusions = [];
foreach ($data as $file => $lines) {
    $path = substr($file, strlen($root) + 1);
    [$start, $end, $reason] = $methodExclusions[$path] ?? [0, 0, ''];
    ksort($lines, SORT_NUMERIC);
    foreach ($lines as $line => $executable) {
        if ($executable === null) {
            continue;
        }
        $methodReason = $line >= $start && $line <= $end ? $reason : null;
        $exclusion = $runtimeLines[$path][$line][1] ?? $fileExclusions[$path] ?? $methodReason;
        if ($exclusion !== null) {
            $exclusions[$path][$line] = $exclusion;
            continue;
        }
        $required++;
        if (empty($hits[$file][$line])) {
            $gaps[$path][] = $line;
        } else {
            $covered++;
        }
    }
}
if ($covered === 0 || $required === 0) {
    throw new RuntimeException('No usable covered lines from the Unit suite.');
}
$missing = $required - $covered;
$summary = [
    'required'   => $required,
    'covered'    => $covered,
    'missing'    => $missing,
    'gaps'       => $gaps,
    'exclusions' => $exclusions
];
$json = json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
if (file_put_contents($reports.'/unit-coverage.json', $json) !== strlen($json)) {
    throw new RuntimeException('Cannot write Unit coverage summary.');
}
printf(
    "Unit-only: %d/%d required lines (%.2f%%); %d missing (see unit-coverage.json).\n",
    $covered,
    $required,
    100 * $covered / $required,
    $missing
);
exit($missing === 0 ? 0 : 1);
