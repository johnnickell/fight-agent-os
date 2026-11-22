<?php

declare(strict_types=1);

namespace Tests\Unit\Sessions;

use App\Adapter\Console\PruneSessionPresentationCommand;
use App\Application\Security\Session\SessionPresentation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Protects explicit cleanup admission and safe operator-visible outcomes
 */
final class PruneSessionPresentationCommandTest extends TestCase
{
    /**
     * Never cleans up without acknowledgement and a canonical bounded batch size
     *
     * @param array<string, bool|string> $options
     */
    #[DataProvider('invalidOptions')]
    public function testRejectsUnacknowledgedOrUnboundedCleanup(array $options): void
    {
        $presentation = $this->createMock(SessionPresentation::class);
        $presentation->expects(self::never())->method('prune');
        $console = new CommandTester(new PruneSessionPresentationCommand($presentation));

        self::assertSame(Command::INVALID, $console->execute($options));
        self::assertSame("Required: --internal --limit=1..1000\n", $console->getDisplay(true));
    }

    /**
     * Supplies invalid cleanup options
     *
     * @return iterable<string, array{array<string, bool|string>}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'missing acknowledgement' => [['--limit' => '10']];
        yield 'leading zero' => [['--internal' => true, '--limit' => '01']];
        yield 'above maximum' => [['--internal' => true, '--limit' => '1001']];
    }

    /**
     * Submits the exact accepted upper bound and reports the actual removed row count
     */
    public function testReportsActualCleanupCount(): void
    {
        $presentation = $this->createMock(SessionPresentation::class);
        $presentation->expects(self::once())->method('prune')->with(1000)->willReturn(12);
        $console = new CommandTester(new PruneSessionPresentationCommand($presentation));

        self::assertSame(Command::SUCCESS, $console->execute(['--internal' => true, '--limit' => '1000']));
        self::assertSame("Removed presentation rows: 12\n", $console->getDisplay(true));
    }

    /**
     * Uses the default batch and reports unavailability without leaking storage failure details
     */
    public function testStorageFailureIsSanitized(): void
    {
        $presentation = $this->createMock(SessionPresentation::class);
        $presentation->expects(self::once())->method('prune')->with(100)
            ->willThrowException(new RuntimeException('private database detail'));
        $console = new CommandTester(new PruneSessionPresentationCommand($presentation));

        self::assertSame(Command::FAILURE, $console->execute(['--internal' => true]));
        self::assertSame("Presentation cleanup unavailable; retry explicitly.\n", $console->getDisplay(true));
    }
}
