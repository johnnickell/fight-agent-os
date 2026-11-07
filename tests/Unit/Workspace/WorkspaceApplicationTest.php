<?php

declare(strict_types=1);

namespace Tests\Unit\Workspace;

use App\Application\Workspace\Workspace\CommandHandler\BootstrapWorkspaceHandler;
use App\Application\Workspace\Workspace\QueryHandler\FindInitialWorkspaceHandler;
use App\Application\Workspace\Workspace\QueryHandler\GetWorkspaceByIdHandler;
use App\Application\Workspace\Workspace\WorkspaceBootstrapAudit;
use App\Domain\Workspace\Workspace\Command\BootstrapWorkspace;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use App\Domain\Workspace\Workspace\Query\GetWorkspaceById;
use App\Domain\Workspace\Workspace\Workspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use App\Domain\Workspace\Workspace\WorkspaceRepository;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\AccessClock;

/**
 * Proves owned bootstrap coordination and safe current-state read semantics
 */
final class WorkspaceApplicationTest extends TestCase
{
    private bool $insideTransaction = false;

    /**
     * Preserves the entry-supplied identity and explicitly audits inside the same generic transaction
     */
    public function testBootstrapCoordinatesStateAndAudit(): void
    {
        $command = new BootstrapWorkspace(WorkspaceId::generate(), UserId::generate());
        self::assertEquals($command, BootstrapWorkspace::fromArray($command->toArray()));
        $clock = new AccessClock(new DateTimeImmutable('2026-11-07T12:00:00Z'));
        $transactionalUnitOfWork = $this->createStub(TransactionalUnitOfWork::class);
        $transactionalUnitOfWork->method('commitTransactional')->willReturnCallback(
            function (callable $operation): mixed {
                $this->insideTransaction = true;
                try {
                    return $operation();
                } finally {
                    $this->insideTransaction = false;
                }
            }
        );
        $stored = null;
        $workspaceRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceRepository->expects(self::once())->method('add')->willReturnCallback(
            function (Workspace $workspace) use (&$stored, $command, $clock): void {
                self::assertTrue($this->insideTransaction);
                self::assertTrue($command->workspaceId->equals($workspace->id));
                self::assertSame($clock->now(), $workspace->createdAt);
                $stored = $workspace;
            }
        );
        $audit = $this->createMock(WorkspaceBootstrapAudit::class);
        $audit->expects(self::once())->method('record')->willReturnCallback(
            function (Workspace $workspace, UserId $operator) use (&$stored, $command): void {
                self::assertTrue($this->insideTransaction);
                self::assertSame($stored, $workspace);
                self::assertTrue($command->operatorId->equals($operator));
            }
        );
        (new BootstrapWorkspaceHandler($workspaceRepository, $audit, $transactionalUnitOfWork, $clock))
            ->handle(CommandMessage::create($command));
        self::assertFalse($this->insideTransaction);
    }

    /**
     * Projects current facts rather than an original-response snapshot and never writes on reads
     */
    public function testCurrentWorkspaceReads(): void
    {
        $workspace = new Workspace(WorkspaceId::generate(), new DateTimeImmutable('2026-11-07T12:00:00Z'));
        $workspaceRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceRepository->expects(self::never())->method('add');
        $workspaceRepository->method('findInitial')->willReturnOnConsecutiveCalls(null, $workspace);
        $workspaceRepository->method('getById')->with($workspace->id)->willReturn($workspace);
        $find = FindInitialWorkspace::fromArray((new FindInitialWorkspace())->toArray());
        $optional = new FindInitialWorkspaceHandler($workspaceRepository);
        self::assertNull($optional->handle(QueryMessage::create($find)));
        $current = $optional->handle(QueryMessage::create($find));
        self::assertInstanceOf(\App\Application\Workspace\Workspace\View\WorkspaceView::class, $current);
        self::assertTrue($workspace->id->equals($current->id));
        self::assertSame($workspace->createdAt, $current->createdAt);
        $get = new GetWorkspaceById($workspace->id);
        self::assertEquals($get, GetWorkspaceById::fromArray($get->toArray()));
        self::assertEquals($current, (new GetWorkspaceByIdHandler($workspaceRepository))->handle(
            QueryMessage::create($get)
        ));
    }

    /**
     * Distinguishes a required absent identity from an internal unavailable lookup
     */
    public function testRequiredAbsence(): void
    {
        $workspaceRepository = $this->createStub(WorkspaceRepository::class);
        $workspaceRepository->method('getById')->willReturn(null);
        $this->expectException(ResourceNotFound::class);
        (new GetWorkspaceByIdHandler($workspaceRepository))->handle(QueryMessage::create(
            new GetWorkspaceById(WorkspaceId::generate())
        ));
    }

    /**
     * Preserves unavailable lookup failures instead of inventing absence
     */
    public function testLookupFailureIsNotAbsence(): void
    {
        $workspaceRepository = $this->createStub(WorkspaceRepository::class);
        $workspaceRepository->method('findInitial')->willThrowException(new RuntimeException('unavailable'));
        $this->expectException(RuntimeException::class);
        (new FindInitialWorkspaceHandler($workspaceRepository))->handle(
            QueryMessage::create(new FindInitialWorkspace())
        );
    }

    /**
     * Rejects mismatched dispatch without interpreting a different message as bootstrap or a read
     */
    public function testMismatchedDispatch(): void
    {
        $workspaceRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceRepository->expects(self::never())->method('add');
        $find = new FindInitialWorkspaceHandler($workspaceRepository);
        $get = new GetWorkspaceByIdHandler($workspaceRepository);
        $bootstrap = new BootstrapWorkspaceHandler(
            $workspaceRepository,
            $this->createStub(WorkspaceBootstrapAudit::class),
            $this->createStub(TransactionalUnitOfWork::class),
            new AccessClock(new DateTimeImmutable('2026-11-07T12:00:00Z'))
        );
        $refused = 0;
        foreach (
            [
            static fn() => $find->handle(QueryMessage::create(new GetWorkspaceById(WorkspaceId::generate()))),
            static fn() => $get->handle(QueryMessage::create(new FindInitialWorkspace())),
            static fn() => $bootstrap->handle(CommandMessage::create(
                new \Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser(
                    UserId::generate(),
                    UserId::generate(),
                    \Fight\AccessControl\Domain\AccessControl\Role\RoleId::generate()
                )
            ))
            ] as $dispatch
        ) {
            try {
                $dispatch();
                self::fail('Mismatched dispatch was accepted');
            } catch (\InvalidArgumentException) {
                $refused++;
            }
        }
        self::assertSame(3, $refused);
    }
}
