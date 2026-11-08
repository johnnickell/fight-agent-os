<?php

declare(strict_types=1);

namespace Tests\Unit\Workspace;

use App\Adapter\Console\BootstrapWorkspaceCommand;
use App\Adapter\Console\SafeConsoleApplication;
use App\Application\Workspace\Workspace\View\WorkspaceView;
use App\Domain\Workspace\Workspace\Command\BootstrapWorkspace;
use App\Domain\Workspace\Workspace\InitialWorkspaceAlreadyExists;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use App\Domain\Workspace\Workspace\Query\GetWorkspaceById;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Query\Query;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Exercises explicit operator admission and safe current-state reconciliation at the Console boundary
 */
final class BootstrapWorkspaceCommandTest extends TestCase
{
    /**
     * Supplies the typed creation ID and operator before void dispatch and presents current creation time
     */
    public function testConfirmedBootstrap(): void
    {
        $operator = UserId::generate();
        $created = null;
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())->method('execute')->willReturnCallback(
            static function (BootstrapWorkspace $command) use ($operator, &$created): void {
                self::assertTrue($operator->equals($command->operatorId));
                $created = new WorkspaceView($command->workspaceId, new DateTimeImmutable('2026-11-07T12:00:00Z'));
            }
        );
        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('fetch')->willReturnCallback(static function (Query $query) use (&$created): ?WorkspaceView {
            if ($query instanceof FindInitialWorkspace) {
                return null;
            }
            self::assertInstanceOf(GetWorkspaceById::class, $query);
            self::assertTrue($created->id->equals($query->workspaceId));

            return $created;
        });
        $console = new CommandTester(new BootstrapWorkspaceCommand($commandBus, $queryBus));
        self::assertSame(0, $console->execute($this->intent($operator)));
        $result = json_decode($console->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($created->id->toString(), $result['workspace_id']);
        self::assertSame('2026-11-07T12:00:00.000000+00:00', $result['created_at']);
    }

    /**
     * Returns authoritative existing identity without another creation command or audit
     */
    public function testRepeatedInvocationReadsCurrentState(): void
    {
        $current = new WorkspaceView(WorkspaceId::generate(), new DateTimeImmutable('2026-11-07T12:00:00Z'));
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::never())->method('execute');
        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('fetch')->willReturn($current);
        $console = new CommandTester(new BootstrapWorkspaceCommand($commandBus, $queryBus));
        self::assertSame(0, $console->execute($this->intent(UserId::generate())));
        self::assertSame($current->id->toString(), json_decode($console->getDisplay(), true)['workspace_id']);
    }

    /**
     * Refuses missing confirmation, invalid attribution, duplicate flags and unknown input without disclosure
     */
    public function testInvalidIntentHasNoDispatch(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::never())->method('execute');
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects(self::never())->method('fetch');
        $application = new SafeConsoleApplication('Fight Agent OS');
        $application->addCommand(new BootstrapWorkspaceCommand($commandBus, $queryBus));
        $application->setAutoExit(false);
        $operator = UserId::generate()->toString();
        foreach (
            [
            ['--internal', '--operator='.$operator],
            ['--internal', '--confirm-bootstrap', '--operator=secret-marker'],
            ['--internal', '--confirm-bootstrap', '--operator='.$operator, '--operator=secret-marker'],
            ['--internal', '--internal', '--confirm-bootstrap', '--operator='.$operator],
            ['--private-secret-marker=secret-marker', '-vvv']
            ] as $arguments
        ) {
            $output = new BufferedOutput();
            self::assertSame(2, $application->run(
                new ArgvInput(['console', 'workspace:bootstrap', ...$arguments]),
                $output
            ));
            self::assertStringNotContainsString('secret-marker', $output->fetch());
        }
    }

    /**
     * Reports a competing initial-slot owner as conflict rather than returning this command's uncommitted ID
     */
    public function testCompetingOwnerIsNotSuccess(): void
    {
        $commandBus = $this->createStub(CommandBus::class);
        $commandBus->method('execute')->willThrowException(new InitialWorkspaceAlreadyExists());
        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('fetch')->willReturn(null);
        $console = new CommandTester(new BootstrapWorkspaceCommand($commandBus, $queryBus));
        self::assertSame(1, $console->execute($this->intent(UserId::generate())));
        self::assertStringContainsString('Another caller', $console->getDisplay());
        self::assertStringNotContainsString('workspace_id', $console->getDisplay());
    }

    /**
     * Keeps read and write failure uncertainty safe without promising rollback or original-response replay
     */
    public function testUncertainOutcomeIsSafe(): void
    {
        foreach (['read', 'write', 'presentation'] as $failure) {
            $commandBus = $this->createStub(CommandBus::class);
            if ($failure === 'write') {
                $commandBus->method('execute')->willThrowException(new RuntimeException('SQL secret-marker'));
            }
            $queryBus = $this->createStub(QueryBus::class);
            $queryBus->method('fetch')->willReturnCallback(static function (Query $query) use ($failure): mixed {
                if ($failure === 'read' || $query instanceof GetWorkspaceById) {
                    throw new RuntimeException('SQL secret-marker');
                }

                return null;
            });
            $console = new CommandTester(new BootstrapWorkspaceCommand($commandBus, $queryBus));
            self::assertSame(1, $console->execute($this->intent(UserId::generate())));
            self::assertStringContainsString('uncertain', $console->getDisplay());
            self::assertStringNotContainsString('secret-marker', $console->getDisplay());
            self::assertStringNotContainsString('original result', $console->getDisplay());
        }
    }

    /**
     * Supplies bounded explicit trusted-operator intent
     *
     * @return array<string, bool|string>
     */
    private function intent(UserId $operator): array
    {
        return ['--internal' => true, '--confirm-bootstrap' => true, '--operator' => $operator->toString()];
    }
}
