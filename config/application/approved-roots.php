<?php

declare(strict_types=1);

use App\Adapter\Installation\ApprovedRootAccess;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(ApprovedRootAccess::class, static fn (): ApprovedRootAccess => new ApprovedRootAccess());
};
