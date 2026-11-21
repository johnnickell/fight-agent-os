<?php

declare(strict_types=1);

namespace Tests\Unit\CredentialDelivery;

use App\Adapter\Console\CredentialDeliveryCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Adapter\CredentialDelivery\CredentialExpiryRunner;
use App\Adapter\CredentialDelivery\CredentialRecoveryRunner;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindExpiredCredentialDeliveries;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Exercises operational admission, ordering and redacted failure output
 */
final class CredentialDeliveryCommandTest extends TestCase
{
    /**
     * Rejects invalid operations before touching delivery configuration or querying work
     *
     * @phpstan-param list<string> $arguments
     */
    #[DataProvider('invalidArguments')]
    public function testInvalidArgumentsDoNotOpenWorkerCapabilities(array $arguments): void
    {
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects(self::never())->method('fetch');
        [$exit, $output] = $this->execute($arguments, $queryBus, static function (): void {
            self::fail('Invalid arguments resolved delivery configuration.');
        });
        self::assertSame(2, $exit);
        self::assertStringNotContainsString('private-input', $output);
    }

    /**
     * Provides examples of ambiguous, invalid or unsupported operational entry input
     *
     * @return iterable<string, array{list<string>}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'missing acknowledgement' => [['run']];
        yield 'duplicate acknowledgement' => [['run', '--internal', '--internal']];
        yield 'duplicate bound' => [['run', '--internal', '--capacity=1', '--capacity=2']];
        yield 'bad ID' => [['status', 'activation', 'private-input', '--internal']];
        yield 'bad purpose' => [['status', 'private-input', 'private-input', '--internal']];
        yield 'invalid expiry bound' => [['expire', '--internal', '--expiry-pages=11']];
        yield 'invalid capacity' => [['run', '--internal', '--capacity=private-input']];
        yield 'delivery option on cleanup' => [['expire', '--internal', '--capacity=1']];
        yield 'lease override' => [['run', '--internal', '--lease-seconds=299']];
        yield 'unknown option' => [['run', '--internal', '--private-input']];
    }

    /**
     * Stops before cleanup when the configured delivery path is not safe to run
     */
    public function testFailedDeliveryPreflightDoesNotStartCleanupOrLeakDiagnostics(): void
    {
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects(self::never())->method('fetch');
        [$exit, $output] = $this->execute(['run', '--internal'], $queryBus, static function (): void {
            throw new RuntimeException('private-input');
        });
        self::assertSame(1, $exit);
        self::assertStringContainsString('outcome is uncertain', $output);
        self::assertStringNotContainsString('private-input', $output);
    }

    /**
     * Runs cleanup before bounded recovery and never opens a provider for cleanup alone
     */
    public function testRunAndExpiryKeepSeparateCapabilitiesAndOrdering(): void
    {
        $steps = [];
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects(self::exactly(3))->method('fetch')->willReturnCallback(
            static function (FindDueCredentialDeliveries|FindExpiredCredentialDeliveries $query) use (&$steps): array {
                $steps[] = $query instanceof FindExpiredCredentialDeliveries ? 'expire' : 'recover';
                self::assertSame($query instanceof FindExpiredCredentialDeliveries ? 50 : 7, $query->getLimit());

                return [];
            }
        );
        $preflight = static function () use (&$steps): void {
            $steps[] = 'preflight';
        };
        [$expire, $expiryOutput] = $this->execute(['expire', '--internal'], $queryBus, $preflight);
        [$run, $runOutput] = $this->execute(['run', '--internal', '--capacity=7'], $queryBus, $preflight);
        self::assertSame([0, 0], [$expire, $run]);
        self::assertSame(['expire', 'preflight', 'expire', 'recover'], $steps);
        self::assertStringContainsString('"expiry":', $expiryOutput);
        self::assertStringNotContainsString('"delivery":', $expiryOutput);
        self::assertStringContainsString('"delivery":', $runOutput);
    }

    /**
     * Invokes the same argument parser and safe console boundary as the real command
     *
     * @phpstan-param list<string> $arguments
     * @phpstan-param \Closure(): void $preflight
     *
     * @return array{int, string}
     */
    private function execute(array $arguments, QueryBus $queryBus, \Closure $preflight): array
    {
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-11-21T00:00:00+00:00'));
        $application = new SafeConsoleApplication();
        $application->setAutoExit(false);
        $application->addCommand(new CredentialDeliveryCommand(
            $queryBus,
            new CredentialExpiryRunner($queryBus, $this->createStub(SynchronousCommandBus::class), $clock),
            new CredentialRecoveryRunner($queryBus, $this->createStub(CommandBus::class), $clock),
            $preflight
        ));
        $output = new BufferedOutput();
        $exit = $application->run(new ArgvInput(['console', 'credential:delivery', ...$arguments]), $output);

        return [$exit, $output->fetch()];
    }
}
