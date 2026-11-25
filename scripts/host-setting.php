<?php

declare(strict_types=1);

use Fight\Runtime\HostSetting;

require_once __DIR__.'/Runtime/autoload.php';

$arguments = $argv ?? [];
exit(HostSetting::run(array_slice($arguments, 1)));
