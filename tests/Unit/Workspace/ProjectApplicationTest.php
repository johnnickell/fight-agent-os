<?php

declare(strict_types=1);

namespace Tests\Unit\Workspace;

use App\Application\Workspace\Project\CommandHandler\RegisterProjectHandler;
use App\Application\Workspace\Project\ProjectRegistrationAudit;
use App\Application\Workspace\Project\QueryHandler\GetProjectByIdHandler;
use App\Application\Workspace\Project\QueryHandler\ListProjectsHandler;
use App\Application\Workspace\Project\QueryHandler\PreviewProjectConflictsHandler;
use App\Domain\Workspace\Project\Command\RegisterProject;
use App\Domain\Workspace\Project\Event\ProjectRegistered;
use App\Domain\Workspace\Project\Project;
use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\ProjectInputRejected;
use App\Domain\Workspace\Project\ProjectMetadata;
use App\Domain\Workspace\Project\ProjectRepository;
use App\Domain\Workspace\Project\ProjectSources;
use App\Domain\Workspace\Project\Query\GetProjectById;
use App\Domain\Workspace\Project\Query\ListProjects;
use App\Domain\Workspace\Project\Query\PreviewProjectConflicts;
use App\Domain\Workspace\Project\SourceDescriptor;
use App\Domain\Workspace\Workspace\Command\BootstrapWorkspace;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\AccessClock;

/**
 * Proves owned Project coordination and intentional typed reads independently of transport admission
 */
final class ProjectApplicationTest extends TestCase
{
    private bool $insideTransaction = false;

    /**
     * Preserves entry identity and explicitly audits before post-commit publication
     */
    public function testAtomicCoordinationAndPostCommitEvent(): void
    {
        $command = $this->command();
        self::assertEquals($command, RegisterProject::fromArray($command->toArray()));
        $transactionalUnitOfWork = $this->transactions();
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRegistrationAudit = $this->createMock(ProjectRegistrationAudit::class);
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $stored = null;
        $projectRepository->expects(self::once())->method('add')->willReturnCallback(function (
            Project $project
        ) use (
            &$stored,
            $command
): void {
            self::assertTrue($this->insideTransaction);
            self::assertSame($command->projectId, $project->id);
            self::assertSame($command->actorId, $project->createdBy);
            self::assertSame($command->sources, $project->sources);
            $stored = $project;
        });
        $projectRegistrationAudit->expects(self::once())->method('record')->willReturnCallback(function (
            Project $project
        ) use (&$stored): void {
            self::assertTrue($this->insideTransaction);
            self::assertSame($stored, $project);
        });
        $eventDispatcher->expects(self::once())->method('trigger')->willReturnCallback(function (
            ProjectRegistered $event
        ) use ($command): void {
            self::assertFalse($this->insideTransaction);
            self::assertSame($command->projectId, $event->projectId);
            self::assertSame($command->workspaceId, $event->workspaceId);
        });
        (new RegisterProjectHandler(
            $projectRepository,
            $projectRegistrationAudit,
            $transactionalUnitOfWork,
            $this->clock(),
            $eventDispatcher
        ))
            ->handle(CommandMessage::create($command));
    }

    /**
     * Emits no success fact when required audit refuses creation
     */
    public function testAuditFailureHasNoSuccessEvent(): void
    {
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects(self::once())->method('add');
        $audit = $this->createStub(ProjectRegistrationAudit::class);
        $audit->method('record')->willThrowException(new RuntimeException('unavailable'));
        $events = $this->createMock(EventDispatcher::class);
        $events->expects(self::never())->method('trigger');
        $this->expectException(RuntimeException::class);
        (new RegisterProjectHandler($projectRepository, $audit, $this->transactions(), $this->clock(), $events))
            ->handle(CommandMessage::create($this->command()));
    }

    /**
     * Projects complete safe detail without writing
     */
    public function testProjectDetail(): void
    {
        $command = $this->command();
        $project = Project::register(
            $command->projectId,
            $command->workspaceId,
            $command->metadata,
            $command->sources,
            $command->actorId,
            $this->clock()->now()
        );
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects(self::never())->method('add');
        $projectRepository->method('getById')->with($command->workspaceId, $command->projectId)->willReturn($project);
        $get = new GetProjectById($command->workspaceId, $command->projectId);
        $view = (new GetProjectByIdHandler($projectRepository))->handle(QueryMessage::create($get));
        self::assertEquals([
            'projectId'         => $command->projectId,
            'workspaceId'       => $command->workspaceId,
            'displayName'       => 'Project',
            'slug'              => 'project',
            'primarySource'     => $command->sources->primary,
            'equivalentSources' => $command->sources->equivalent,
            'createdBy'         => $command->actorId,
            'updatedBy'         => $command->actorId,
            'createdAt'         => new DateTimeImmutable('2026-11-08T05:00:00Z'),
            'updatedAt'         => new DateTimeImmutable('2026-11-08T05:00:00Z'),
            'metadataRevision'  => 1,
            'sourceRevision'    => 1,
            'lifecycleRevision' => 1
        ], get_object_vars($view));
    }

    /**
     * Returns a complete bounded page and detects the extra-item sentinel without writing
     */
    public function testPopulatedProjectPage(): void
    {
        $command = $this->command();
        $project = Project::register(
            $command->projectId,
            $command->workspaceId,
            $command->metadata,
            $command->sources,
            $command->actorId,
            $this->clock()->now()
        );
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects(self::never())->method('add');
        $list = new ListProjects($command->workspaceId, 2, 1);
        $projectRepository->expects(self::once())->method('slice')->with($command->workspaceId, 2, 2)
            ->willReturn([$project, $project]);
        $page = (new ListProjectsHandler($projectRepository))->handle(QueryMessage::create($list));
        self::assertCount(1, $page->items);
        self::assertEquals([
            'projectId'         => $command->projectId,
            'workspaceId'       => $command->workspaceId,
            'displayName'       => 'Project',
            'slug'              => 'project',
            'primarySource'     => $command->sources->primary,
            'equivalentSources' => $command->sources->equivalent,
            'createdBy'         => $command->actorId,
            'updatedBy'         => $command->actorId,
            'createdAt'         => new DateTimeImmutable('2026-11-08T05:00:00Z'),
            'updatedAt'         => new DateTimeImmutable('2026-11-08T05:00:00Z'),
            'metadataRevision'  => 1,
            'sourceRevision'    => 1,
            'lifecycleRevision' => 1
        ], get_object_vars($page->items[0]));
        self::assertTrue($page->hasMore);
        self::assertSame([2, 1], [$page->offset, $page->limit]);
    }

    /**
     * Returns an empty page with the requested bounds and no further items or writes
     */
    public function testEmptyProjectPage(): void
    {
        $workspaceId = WorkspaceId::generate();
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects(self::never())->method('add');
        $projectRepository->expects(self::once())->method('slice')->with($workspaceId, 0, 26)->willReturn([]);
        $page = (new ListProjectsHandler($projectRepository))->handle(QueryMessage::create(
            new ListProjects($workspaceId)
        ));
        self::assertSame([], $page->items);
        self::assertFalse($page->hasMore);
        self::assertSame([0, 25], [$page->offset, $page->limit]);
    }

    /**
     * Reports claimed and clear advisory source membership without writing
     */
    #[DataProvider('conflictClaims')]
    public function testAdvisoryConflicts(bool $claimed): void
    {
        $command = $this->command();
        $preview = new PreviewProjectConflicts($command->workspaceId, $command->metadata, $command->sources);
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects(self::never())->method('add');
        $projectRepository->method('hasActiveSlug')->willReturn($claimed);
        $projectRepository->method('hasActiveSource')->willReturn($claimed);
        $conflicts = (new PreviewProjectConflictsHandler($projectRepository))->handle(QueryMessage::create($preview));
        self::assertSame($claimed, $conflicts->slugClaimed);
        self::assertSame($claimed, $conflicts->sourceClaimed);
    }

    /**
     * Supplies occupied and available advisory claims
     *
     * @return iterable<string, array{bool}>
     */
    public static function conflictClaims(): iterable
    {
        yield 'claimed' => [true];
        yield 'clear' => [false];
    }

    /**
     * Preserves query message serialization including absent source descriptors
     */
    public function testReadMessageRoundTrips(): void
    {
        $command = $this->command();
        $get = new GetProjectById($command->workspaceId, $command->projectId);
        self::assertEquals($get, GetProjectById::fromArray($get->toArray()));
        $list = new ListProjects($command->workspaceId, 2, 1);
        self::assertEquals($list, ListProjects::fromArray($list->toArray()));
        $preview = new PreviewProjectConflicts($command->workspaceId, $command->metadata, $command->sources);
        self::assertEquals($preview, PreviewProjectConflicts::fromArray($preview->toArray()));
        $empty = new PreviewProjectConflicts($command->workspaceId, $command->metadata, new ProjectSources());
        self::assertEquals($empty, PreviewProjectConflicts::fromArray($empty->toArray()));
    }

    /**
     * Preserves creation message serialization without source descriptors
     */
    public function testSourcelessCreationMessageRoundTrip(): void
    {
        $command = $this->command();
        $noSources = new RegisterProject(
            $command->projectId,
            $command->workspaceId,
            $command->actorId,
            $command->metadata,
            new ProjectSources()
        );
        self::assertEquals($noSources, RegisterProject::fromArray($noSources->toArray()));
    }

    /**
     * Distinguishes required absence from infrastructure failure
     */
    public function testRequiredAbsence(): void
    {
        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('getById')->willReturn(null);
        $this->expectException(ResourceNotFound::class);
        (new GetProjectByIdHandler($projectRepository))->handle(QueryMessage::create(new GetProjectById(
            WorkspaceId::generate(),
            ProjectId::generate()
        )));
    }

    /**
     * Preserves an unknown lookup failure instead of returning required absence
     */
    public function testUnknownLookupFailure(): void
    {
        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('getById')->willThrowException(new RuntimeException('unavailable'));
        $this->expectException(RuntimeException::class);
        (new GetProjectByIdHandler($projectRepository))->handle(QueryMessage::create(new GetProjectById(
            WorkspaceId::generate(),
            ProjectId::generate()
        )));
    }

    /**
     * Rejects unbounded direct query requests
     */
    #[DataProvider('invalidPages')]
    public function testBoundedPages(int $offset, int $limit): void
    {
        $this->expectException(ProjectInputRejected::class);
        new ListProjects(WorkspaceId::generate(), $offset, $limit);
    }

    /**
     * Supplies rejected pagination bounds
     *
     * @return iterable<array{int, int}>
     */
    public static function invalidPages(): iterable
    {
        yield [-1, 25];
        yield [100001, 25];
        yield [0, 0];
        yield [0, 101];
    }

    /**
     * Rejects mismatched dispatch without interpreting another owner's intent
     */
    public function testRegisterProjectRejectsMismatchedCommand(): void
    {
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects(self::never())->method('add');
        $handler = new RegisterProjectHandler(
            $projectRepository,
            $this->createStub(ProjectRegistrationAudit::class),
            $this->transactions(),
            $this->clock(),
            $this->createStub(EventDispatcher::class)
        );
        $this->expectException(\InvalidArgumentException::class);
        $handler->handle(CommandMessage::create(new BootstrapWorkspace(WorkspaceId::generate(), UserId::generate())));
    }

    /**
     * Rejects mismatched queries without writing
     *
     * @param class-string<GetProjectByIdHandler|ListProjectsHandler|PreviewProjectConflictsHandler> $handlerClass
     */
    #[DataProvider('mismatchedQueryHandlers')]
    public function testQueryRejectsMismatchedMessage(string $handlerClass): void
    {
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects(self::never())->method('add');
        $handler = new $handlerClass($projectRepository);
        $this->expectException(\InvalidArgumentException::class);
        $handler->handle(QueryMessage::create(new FindInitialWorkspace()));
    }

    /**
     * Names every Project query rejection scenario
     *
     * @return iterable<string, array{
     *     class-string<GetProjectByIdHandler|ListProjectsHandler|PreviewProjectConflictsHandler>
     * }>
     */
    public static function mismatchedQueryHandlers(): iterable
    {
        yield 'detail rejects initial Workspace lookup' => [GetProjectByIdHandler::class];
        yield 'pagination rejects initial Workspace lookup' => [ListProjectsHandler::class];
        yield 'conflict preview rejects initial Workspace lookup' => [PreviewProjectConflictsHandler::class];
    }

    /**
     * Supplies a source-bearing creation intent with owning generated identities
     */
    private function command(): RegisterProject
    {
        return new RegisterProject(
            ProjectId::generate(),
            WorkspaceId::generate(),
            UserId::generate(),
            new ProjectMetadata('Project', 'project'),
            new ProjectSources(
                SourceDescriptor::fromInput([
                    'type' => 'git_network_v1', 'locator' => 'https://github.com/team/project'
                ]),
                [SourceDescriptor::fromInput([
                    'type' => 'git_network_v1', 'locator' => 'https://mirror.test/team/project'
                ])]
            )
        );
    }

    /**
     * Supplies deterministic business time
     */
    private function clock(): AccessClock
    {
        return new AccessClock(new DateTimeImmutable('2026-11-08T05:00:00Z'));
    }

    /**
     * Models the generic transaction boundary without claiming database rollback evidence
     */
    private function transactions(): TransactionalUnitOfWork
    {
        $transactionalUnitOfWork = $this->createStub(TransactionalUnitOfWork::class);
        $transactionalUnitOfWork->method('commitTransactional')->willReturnCallback(function (
            callable $operation
        ): mixed {
            $this->insideTransaction = true;
            try {
                return $operation();
            } finally {
                $this->insideTransaction = false;
            }
        });

        return $transactionalUnitOfWork;
    }
}
