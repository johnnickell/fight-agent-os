<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Installation;

use App\Adapter\Installation\ApprovedRootAccess;
use App\Adapter\Installation\ApprovedRootFilesystem;
use App\Adapter\Installation\ApprovedRootView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the safe application view of configured and observed root access
 */
final class ApprovedRootAccessTest extends TestCase
{
    private const string TARGET = '/roots/example-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /**
     * Distinguishes configured access from exact mount and permission observations
     *
     * @param string                            $access
     * @param string                            $mount
     * @param boolean                           $permitted
     * @param array{bool, bool, bool, ?string}    $expected
     */
    #[DataProvider('observations')]
    public function testAccessFacts(string $access, string $mount, bool $permitted, array $expected): void
    {
        $target = self::TARGET;
        $mode = in_array($mount, ['ro', 'rw'], true) ? $mount : 'ro';
        $line = "2 1 0:1 / {$target} {$mode},nosuid - tmpfs tmpfs rw\n";
        $table = match ($mount) {
            'missing' => str_replace($target, $target.'-prior', $line),
            'nested' => $line.str_replace($target, $target.'/nested', $line),
            'duplicate' => $line.$line,
            'malformed' => "invalid\n",
            'truncated' => 'incomplete',
            'unavailable' => false,
            default => $line,
        };
        if (is_string($table)) {
            $table = "1 0 0:1 / / rw - tmpfs tmpfs rw\n".$table;
        }
        $filesystem = $this->createMock(ApprovedRootFilesystem::class);
        $filesystem->method('hasConfigurationDirectory')->with('/settings')->willReturn(true);
        $filesystem->method('read')->willReturnMap([
            ['/settings/roots.json', 65537, $this->configuration([
                'example' => ['access' => $access, 'target' => $target, 'path' => '/private/example']
            ])],
            ['/kernel/mountinfo', 262145, $table]
        ]);
        if ($mount === $access) {
            $filesystem->expects($this->once())->method('permissions')->with($target, $access === 'rw')
                ->willReturn(['readable' => $permitted, 'writable' => $permitted]);
        } else {
            $filesystem->expects($this->never())->method('permissions');
        }
        $view = $this->probe($filesystem)->observe();

        [$mounted, $readable, $writable, $reason] = $expected;
        self::assertEquals([
            new ApprovedRootView('root:example', true, $mounted, $readable, $access === 'rw', $writable, $reason)
        ], $view['roots']);
        $failedMountTable = in_array($mount, ['unavailable', 'malformed', 'truncated'], true);
        self::assertSame($failedMountTable ? 'mount_table_unavailable' : null, $view['reason']);
        self::assertStringNotContainsString('/private/', json_encode($view, JSON_THROW_ON_ERROR));
    }

    /**
     * Supplies ordinary and unavailable observation states
     *
     * @return iterable<string, array{string, string, bool, array{bool, bool, bool, ?string}}>
     */
    public static function observations(): iterable
    {
        yield 'read-only' => ['ro', 'ro', true, [true, true, false, null]];
        yield 'write granted' => ['rw', 'rw', true, [true, true, true, null]];
        yield 'old generation only' => ['ro', 'missing', true, [false, false, false, 'exact_mount_not_observed']];
        yield 'permissions denied' => ['rw', 'rw', false, [true, false, false, 'access_unavailable']];
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
     * Fails closed on missing and malformed configuration without exposing internals
     */
    public function testConfigurationFailures(): void
    {
        $filesystem = $this->createStub(ApprovedRootFilesystem::class);
        $filesystem->method('hasConfigurationDirectory')->willReturn(false);
        $probe = $this->probe($filesystem);
        self::assertSame(['roots' => [], 'reason' => 'no_configuration_mount'], $probe->observe());
    }

    /**
     * Validates each bounded settings snapshot before reporting its roots
     *
     * @param array<string, array<string, string>>|null $roots
     * @param string|null                             $reason
     */
    #[DataProvider('configurations')]
    public function testConfiguration(?array $roots, ?string $reason): void
    {
        $filesystem = $this->createStub(ApprovedRootFilesystem::class);
        $filesystem->method('hasConfigurationDirectory')->willReturn(true);
        $filesystem->method('read')->willReturnMap([
            ['/settings/roots.json', 65537, $roots === null ? false : $this->configuration($roots)],
            ['/kernel/mountinfo', 262145, false]
        ]);
        self::assertSame(['roots' => [], 'reason' => $reason], $this->probe($filesystem)->observe());
    }

    /**
     * Supplies missing, malformed and empty settings
     *
     * @return iterable<string, array{array<string, array<string, string>>|null, ?string}>
     */
    public static function configurations(): iterable
    {
        yield 'unreadable' => [null, 'configuration_unavailable'];
        yield 'invalid name' => [
            ['private/name' => ['access' => 'ro', 'target' => self::TARGET]], 'configuration_unavailable'
        ];
        yield 'invalid target' => [
            ['example' => ['access' => 'ro', 'target' => '/private/example']], 'configuration_unavailable'
        ];
        yield 'empty' => [[], null];
    }

    /**
     * Builds bounded settings fixture data
     *
     * @param array<string, array<string, string>> $roots
     */
    private function configuration(array $roots): string
    {
        return json_encode([
            'x-fight-approved-roots' => ['version' => 1, 'roots' => $roots]
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Constructs the capability over in-memory observations
     */
    private function probe(ApprovedRootFilesystem $filesystem): ApprovedRootAccess
    {
        return new ApprovedRootAccess($filesystem, '/settings/roots.json', '/roots', '/kernel/mountinfo');
    }
}
