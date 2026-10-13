<?php

declare(strict_types=1);

require sprintf('%s/vendor/autoload.php', dirname(__DIR__));

// Ephemeral test-only CSRF configuration; no test key is committed or shared with an installation.
putenv('APP_CSRF_MAC_KEY='.bin2hex(random_bytes(32)));
putenv('APP_BROWSER_ORIGIN=https://agent-os.test');
putenv('APP_ACCESS_TOKEN_CONFIG='.json_encode([
    'algorithm'  => 'HS256',
    'issuer'     => 'https://agent-os.test',
    'audience'   => 'agent-os-api',
    'active_kid' => 'test-active',
    'keys'       => [['kid' => 'test-active', 'secret_hex' => bin2hex(random_bytes(32))]]
], JSON_THROW_ON_ERROR));
