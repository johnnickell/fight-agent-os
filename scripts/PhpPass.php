<?php

declare(strict_types=1);

namespace Tooling\Build;

use RuntimeException;

/**
 * Class PhpPass
 *
 * PHP check inventory shared by standalone verification and the full build
 */
final class PhpPass
{
    /**
     * Runs the PHP checks using the caller's phase and evidence writer
     */
    public static function run(string $root, callable $execute): int
    {
        if (!extension_loaded('xdebug')) {
            throw new RuntimeException('PHP verification requires installed Xdebug. See docs/engineering/BUILD.md.');
        }

        $steps = [
            'Composer version'       => ['composer', '--version', '--no-interaction'],
            'Composer manifest/lock' => ['composer', 'validate', '--strict', '--no-interaction'],
            'PHP syntax/namespaces'  => ['php', 'scripts/php_syntax.php'],
            'PHP style'              => ['php', 'vendor/bin/phpcs', '-s'],
            'PHP types'              => [
                'php', 'vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=512M'
            ],
            'PHP dependencies'       => [
                'php', 'vendor/bin/deptrac', 'analyse', '--config-file=deptrac.php', '--formatter=table',
                '--no-progress', '--report-uncovered', '--fail-on-uncovered'
            ],
            'Rector (dry-run)'       => ['php', 'vendor/bin/rector', 'process', '--dry-run', '--no-progress-bar'],
            'OpenAPI freshness'      => ['php', 'scripts/openapi.php', 'check'],
            'Validation freshness'   => ['php', 'scripts/validations.php', 'check'],
            'Backend coverage'       => ['php', 'scripts/backend_tests.php']
        ];
        foreach ($steps as $name => $command) {
            $environment = ['COMPOSER_ROOT_VERSION' => 'dev-develop', 'COMPOSER_DISABLE_NETWORK' => '1'];
            if ($name === 'Backend coverage') {
                $environment['APP_ENV'] = 'test';
                $environment['XDEBUG_MODE'] = 'coverage';
            }
            $exit = $execute($name, $command, $root, $environment);
            if ($exit !== 0) {
                fwrite(STDERR, 'PHP verification failed during '.$name.".\n");

                return $exit;
            }
        }

        return 0;
    }
}
