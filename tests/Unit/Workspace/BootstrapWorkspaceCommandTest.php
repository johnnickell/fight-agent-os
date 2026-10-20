<?php

declare(strict_types=1);

namespace Tests\Unit\Workspace;

use App\Adapter\Console\BootstrapWorkspaceCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Application\Workspace\BootstrapWorkspace;
use App\Application\Workspace\RegistrationStore;
use App\Application\Workspace\WorkspaceView;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Exercises explicit installation intent, safe output and failure recovery at the real Console boundary
 */
final class BootstrapWorkspaceCommandTest extends TestCase
{
    /**
     * Creates one internally generated Workspace only after explicit trusted confirmation
     */
    public function testConfirmedBootstrap(): void
    {
        $store = $this->createMock(RegistrationStore::class);
        $store->method('initialWorkspace')->willReturn(null);
        $workspace = null;
        $store->expects(self::once())->method('bootstrap')->willReturnCallback(
            static function (WorkspaceView $view) use (&$workspace): void {
                $workspace = $view;
            }
        );
        $command = new CommandTester($this->command($store));
        self::assertSame(0, $command->execute([
            '--internal' => true, '--confirm-bootstrap' => true, '--operator' => UserId::generate()->toString()
        ]));
        $result = json_decode($command->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($workspace->id->toString(), $result['workspace_id']);
        self::assertSame('2026-10-20T12:00:00.123456+00:00', $result['created_at']);
    }

    /**
     * Rejects absent confirmation and malformed attribution without echoing private input
     */
    public function testInvalidIntentHasNoBootstrap(): void
    {
        $store = $this->createMock(RegistrationStore::class);
        $store->expects(self::never())->method('bootstrap');
        $command = new CommandTester($this->command($store));
        self::assertSame(2, $command->execute(['--internal' => true, '--operator' => 'secret-marker']));
        self::assertStringNotContainsString('secret-marker', $command->getDisplay());
        self::assertSame(2, $command->execute([
            '--internal' => true, '--confirm-bootstrap' => true, '--operator' => 'secret-marker'
        ]));
        self::assertStringNotContainsString('secret-marker', $command->getDisplay());
    }

    /**
     * Rejects repeated attribution instead of silently choosing the last supplied identity
     */
    public function testRepeatedOperator(): void
    {
        $store = $this->createMock(RegistrationStore::class);
        $store->expects(self::never())->method('bootstrap');
        $application = new SafeConsoleApplication('Fight Agent OS');
        $application->addCommand($this->command($store));
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $operator = UserId::generate()->toString();
        self::assertSame(2, $application->run(new ArgvInput([
            'console', 'workspace:bootstrap', '--internal', '--confirm-bootstrap',
            '--operator='.$operator, '--operator=secret-marker'
        ]), $output));
        self::assertStringNotContainsString('secret-marker', $output->fetch());
    }

    /**
     * Reports uncertainty rather than asserting rollback after an unavailable bootstrap store
     */
    public function testUncertainOutcomeIsSafe(): void
    {
        $store = $this->createMock(RegistrationStore::class);
        $store->method('initialWorkspace')->willThrowException(new RuntimeException('SQL secret-marker'));
        $command = new CommandTester($this->command($store));
        self::assertSame(1, $command->execute([
            '--internal' => true, '--confirm-bootstrap' => true, '--operator' => UserId::generate()->toString()
        ]));
        self::assertStringContainsString('uncertain', $command->getDisplay());
        self::assertStringNotContainsString('secret-marker', $command->getDisplay());
        self::assertStringNotContainsString('SQL', $command->getDisplay());
    }

    /**
     * Constructs the actual use case with deterministic time and an isolated transaction capability
     */
    private function command(RegistrationStore $store): BootstrapWorkspaceCommand
    {
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-20T12:00:00.123456+00:00'));
        $transactions = $this->createStub(TransactionalUnitOfWork::class);
        $transactions->method('commitTransactional')->willReturnCallback(static fn(callable $op): mixed => $op());

        return new BootstrapWorkspaceCommand(new BootstrapWorkspace($store, $transactions, $clock));
    }
}
