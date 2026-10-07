<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$reports = $root.'/.runs/backend-coverage';
// This is a locally generated PHPUnit artifact, never an externally supplied PHP file.
$coverage = require $reports.'/coverage.php';
if (!$coverage instanceof CodeCoverage) {
    throw new RuntimeException('Missing coherent PHPUnit coverage.');
}

$fileExclusions = [
    'src/Adapter/Persistence/Guard/DatabaseTargetGuard.php' => 'Destructive test-tool safety, verified directly',
    'src/Adapter/Validation/PublicForms.php'                => 'Explicit publication composition registry'
];
$methodExclusions = [];
$excludedMethods = [
    'Application/Security/Csrf/QueryHandler/GetCsrfProofHandler' => 'queryRegistration',
    'Adapter/Persistence/ActivationGrantRecords'                 => '__construct',
    'Adapter/Persistence/ActivationGrantTransitions'             => '__construct',
    'Adapter/Persistence/EmailChangeGrantRecords'                => '__construct',
    'Adapter/Persistence/ExpiredCredentialDeliveryRecords'       => '__construct',
    'Adapter/Persistence/PasswordResetGrantRecords'              => '__construct',
    'Adapter/Persistence/PostgresAtomicOperation'                => '__construct',
    'Adapter/Persistence/PostgresUniqueConstraintRace'           => '__construct',
    'Adapter/Persistence/RefreshSessionRecords'                  => '__construct'
];
foreach ($excludedMethods as $class => $name) {
    $method = new ReflectionMethod('App\\'.str_replace('/', '\\', $class), $name);
    $reason = 'Static query-bus registration metadata, not handler behavior';
    if ($name === '__construct') {
        $reason = 'Empty private constructor prevents instantiation of static adapter composition';
    }
    $methodExclusions['src/'.$class.'.php'] = [
        'start'  => $method->getStartLine(),
        'end'    => $method->getEndLine(),
        'reason' => $reason
    ];
}

$data = $coverage->getData()->lineCoverage();
$source = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src'));
foreach ($source as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && !array_key_exists($file->getPathname(), $data)) {
        throw new RuntimeException('Coverage omitted owned source: '.$file->getPathname());
    }
}
$summary = ['groups' => [], 'files' => [], 'exclusions' => []];
$missingRequired = 0;
foreach ($data as $file => $lines) {
    if (!str_starts_with($file, $root.'/src/')) {
        throw new RuntimeException('Coverage contains a file outside owned source.');
    }
    $path = substr($file, strlen($root) + 1);
    $group = str_starts_with($path, 'src/Adapter/') ? 'Adapter' : 'Domain/Application';
    $counts = ['lines' => 0, 'covered' => 0, 'unit' => 0, 'integration' => 0, 'functional' => 0];
    $uncovered = [];
    $excluded = [];
    foreach ($lines as $line => $tests) {
        if ($tests === null) {
            continue;
        }
        $method = $methodExclusions[$path] ?? null;
        $reason = $fileExclusions[$path] ?? (
            $method !== null && $line >= $method['start'] && $line <= $method['end'] ? $method['reason'] : null
        );
        if ($reason !== null) {
            $excluded[$line] = $reason;
            continue;
        }
        $counts['lines']++;
        $counts['covered'] += $tests === [] ? 0 : 1;
        $suites = ['unit' => 'Unit', 'integration' => 'Integration', 'functional' => 'Functional'];
        foreach ($suites as $key => $namespace) {
            foreach ($tests as $test) {
                if (str_starts_with($test, 'Tests\\'.$namespace.'\\')) {
                    $counts[$key]++;
                    break;
                }
            }
        }
        if ($tests === []) {
            $uncovered[] = $line;
        }
    }
    if ($excluded !== []) {
        ksort($excluded, SORT_NUMERIC);
        $summary['exclusions'][$path] = $excluded;
    }
    sort($uncovered, SORT_NUMERIC);
    $summary['files'][$path] = $counts + ['uncovered' => $uncovered];
    foreach ($counts as $key => $value) {
        $summary['groups'][$group][$key] = ($summary['groups'][$group][$key] ?? 0) + $value;
    }
    if ($group === 'Domain/Application') {
        $missingRequired += count($uncovered);
    }
}
ksort($summary['files']);
ksort($summary['exclusions']);
foreach (['Domain/Application', 'Adapter'] as $group) {
    $counts = $summary['groups'][$group] ?? null;
    if ($counts === null || $counts['lines'] === 0) {
        throw new RuntimeException('Coverage denominator is absent for '.$group.'.');
    }
    printf(
        "%s: %d/%d lines (%.2f%%); Unit %d, Integration/Postgres %d, Functional %d\n",
        $group,
        $counts['covered'],
        $counts['lines'],
        100 * $counts['covered'] / $counts['lines'],
        $counts['unit'],
        $counts['integration'],
        $counts['functional']
    );
    $adapter = $group === 'Adapter';
    $select = static fn(string $path): bool => str_starts_with($path, 'src/Adapter/') === $adapter;
    $report = [
        'counts'     => $counts,
        'files'      => array_filter($summary['files'], $select, ARRAY_FILTER_USE_KEY),
        'exclusions' => array_filter($summary['exclusions'], $select, ARRAY_FILTER_USE_KEY)
    ];
    $filename = $adapter ? 'adapter.json' : 'domain-application.json';
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    if (file_put_contents($reports.'/'.$filename, $json) === false) {
        throw new RuntimeException('Cannot write '.$group.' coverage report.');
    }
}
$json = json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
if (file_put_contents($reports.'/summary.json', $json) === false) {
    throw new RuntimeException('Cannot write coverage summary.');
}
if ($missingRequired !== 0) {
    fwrite(STDERR, sprintf("Required Domain/Application coverage is missing %d lines.\n", $missingRequired));
    exit(1);
}
fwrite(STDOUT, "Domain/Application threshold passed; Adapter gaps remain visible in summary.json.\n");
