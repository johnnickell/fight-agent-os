<?php

declare(strict_types=1);

require sprintf('%s/vendor/autoload.php', dirname(__DIR__));

// Ephemeral test-only CSRF configuration; no test key is committed or shared with an installation.
putenv('APP_CSRF_MAC_KEY='.bin2hex(random_bytes(32)));
putenv('APP_BROWSER_ORIGIN=https://agent-os.test');
