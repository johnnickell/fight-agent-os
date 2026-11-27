<?php

declare(strict_types=1);

use Fight\Runtime\RuntimeCommand;

require_once __DIR__.'/Runtime/autoload.php';

umask(0077);
try {
    $arguments = $argv ?? [];
    exit(RuntimeCommand::run(array_slice($arguments, 1)));
} catch (RuntimeException $error) {
    fwrite(STDERR, 'Runtime operation incomplete: '.$error->getMessage()."\n");
    exit(1);
} catch (JsonException | TypeError | ValueError $error) {
    fwrite(STDERR, "Runtime operation incomplete: invalid native configuration or result; inspect privately.\n");
    exit(1);
}
