<?php

declare(strict_types=1);

use Tooling\Planning\PlanningPortfolio;

require __DIR__.'/PlanningPortfolio.php';

$arguments = array_slice($argv ?? [], 1);
$usage = "Usage: php scripts/planning_portfolio.php [--write]\n";
if ($arguments === ['--help'] || $arguments === ['-h']) {
    echo $usage;
    echo "Validate planning offline. Use --write to close eligible parents and refresh generated views.\n";
    exit(0);
}
if ($arguments !== [] && $arguments !== ['--write']) {
    fwrite(STDERR, $usage);
    exit(2);
}
try {
    exit(new PlanningPortfolio(dirname(__DIR__))->run($arguments === ['--write']));
} catch (RuntimeException $exception) {
    fwrite(STDERR, "Planning validation failed:\n- ".$exception->getMessage()."\n");
    exit(1);
}
