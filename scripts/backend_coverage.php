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
        $exclusion = $fileExclusions[$path] ?? ($line >= $start && $line <= $end ? $reason : null);
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
