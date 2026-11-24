<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Installation;

use App\Adapter\Installation\ApprovedRootAccess;
use App\Adapter\Installation\ApprovedRootView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verify the safe application view of configured and observed root access
 */
final class ApprovedRootAccessTest extends TestCase
{
    private string $directory;
    private string $target;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/fight-roots-'.bin2hex(random_bytes(8));
        $this->target = $this->directory.'/example-'.str_repeat('a', 64);
        mkdir($this->directory, 0700);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        if (is_dir($this->target)) {
            rmdir($this->target);
        }
        foreach (['roots.json', 'mountinfo'] as $file) {
            if (is_file($this->directory.'/'.$file)) {
                unlink($this->directory.'/'.$file);
            }
        }
        rmdir($this->directory);
    }

    /**
     * Distinguish configured access from exact current mount and process permissions
     *
     * @param string                           $access
     * @param string                           $mount
     * @param boolean                          $directory
     * @param array{bool, bool, bool, ?string} $expected
     */
    #[DataProvider('observations')]
    public function testAccessFacts(string $access, string $mount, bool $directory, array $expected): void
    {
        if ($directory) {
            mkdir($this->target, 0700);
        }
        $this->configure(['example' => ['access' => $access, 'target' => $this->target, 'path' => '/private/example']]);
        if ($mount !== 'unavailable') {
            $table = "1 0 0:1 / / rw - tmpfs tmpfs rw\n";
            $mode = $mount === 'nested' || $mount === 'duplicate' ? 'ro' : $mount;
            $line = "2 1 0:1 / {$this->target} {$mode},nosuid - tmpfs tmpfs rw\n";
            $table .= match ($mount) {
                'missing' => str_replace($this->target, $this->target.'-prior', $line),
                'nested' => $line.str_replace($this->target, $this->target.'/nested', $line),
                'duplicate' => $line.$line,
                'malformed' => "invalid\n",
                'truncated' => 'incomplete',
                default => $line,
            };
            file_put_contents($this->directory.'/mountinfo', $table);
        }
        $view = $this->probe()->observe();
        [$mounted, $readable, $writable, $reason] = $expected;
        self::assertEquals([
            new ApprovedRootView('root:example', true, $mounted, $readable, $access === 'rw', $writable, $reason)
        ], $view['roots']);
        $failedMountTable = in_array($mount, ['unavailable', 'malformed', 'truncated'], true);
        $mountTableReason = $failedMountTable ? 'mount_table_unavailable' : null;
        self::assertSame($mountTableReason, $view['reason']);
        self::assertStringNotContainsString('/private/', json_encode($view, JSON_THROW_ON_ERROR));
    }

    /**
     * Supply ordinary and unavailable observation states
     *
     * @return iterable<string, array{string, string, bool, array{bool, bool, bool, ?string}}>
     */
    public static function observations(): iterable
    {
        yield 'read-only' => ['ro', 'ro', true, [true, true, false, null]];
        yield 'write granted' => ['rw', 'rw', true, [true, true, true, null]];
        yield 'old generation only' => ['ro', 'missing', true, [false, false, false, 'exact_mount_not_observed']];
        yield 'inaccessible' => ['rw', 'rw', false, [true, false, false, 'access_unavailable']];
        yield 'unexpected mode' => ['ro', 'rw', true, [true, false, false, 'unexpected_mount']];
        yield 'nested mount' => ['ro', 'nested', true, [true, false, false, 'unexpected_mount']];
        yield 'duplicate mount' => ['ro', 'duplicate', true, [true, false, false, 'unexpected_mount']];
        yield 'unavailable mount metadata' => [
            'rw', 'unavailable', true, [false, false, false, 'mount_table_unavailable']
        ];
        yield 'malformed mount metadata' => [
            'rw', 'malformed', true, [false, false, false, 'mount_table_unavailable']
        ];
        yield 'incomplete mount metadata' => [
            'rw', 'truncated', true, [false, false, false, 'mount_table_unavailable']
        ];
    }

    /**
     * Fail closed on missing and malformed configuration without exposing internals
     */
    public function testConfigurationFailures(): void
    {
        self::assertSame(['roots' => [], 'reason' => 'no_configuration_mount'], $this->probe('/missing')->observe());
        self::assertSame(['roots' => [], 'reason' => 'configuration_unavailable'], $this->probe()->observe());
        $this->configure(['private/name' => ['access' => 'ro', 'target' => $this->target]]);
        self::assertSame(['roots' => [], 'reason' => 'configuration_unavailable'], $this->probe()->observe());
        $this->configure(['example' => ['access' => 'ro', 'target' => '/private/example']]);
        self::assertSame(['roots' => [], 'reason' => 'configuration_unavailable'], $this->probe()->observe());
        $this->configure([]);
        self::assertSame(['roots' => [], 'reason' => null], $this->probe()->observe());
    }

    /**
     * Write a bounded fixture for the application observation
     *
     * @param array<string, array<string, string>> $roots
     */
    private function configure(array $roots): void
    {
        file_put_contents($this->directory.'/roots.json', json_encode([
            'x-fight-approved-roots' => ['version' => 1, 'roots' => $roots]
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Construct the capability with disposable observations
     */
    private function probe(string $missing = ''): ApprovedRootAccess
    {
        return new ApprovedRootAccess(
            $this->directory.$missing.'/roots.json',
            $this->directory,
            $this->directory.'/mountinfo'
        );
    }
}
