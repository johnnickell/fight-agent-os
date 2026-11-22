<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Projects\GetProjectAction;
use App\Adapter\Http\Action\Api\V1\Projects\ListProjectsAction;
use App\Adapter\Http\Action\Api\V1\Projects\PreviewProjectConflictsAction;
use App\Adapter\Http\Action\Api\V1\Projects\RegisterProjectAction;
use App\Adapter\Http\Action\Api\V1\References\GenerateExceptionalReferenceAction;
use App\Adapter\Http\Api\V1\Projects\ProjectWorkspaceAdmission;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Responder\Api\V1\Projects\GetProjectResponder;
use App\Adapter\Http\Responder\Api\V1\Projects\ListProjectsResponder;
use App\Adapter\Http\Responder\Api\V1\Projects\PreviewProjectConflictsResponder;
use App\Adapter\Http\Responder\Api\V1\Projects\RegisterProjectResponder;
use App\Adapter\Http\Responder\Api\V1\References\GenerateExceptionalReferenceResponder;
use App\Adapter\Reference\ExceptionalReferences;
use App\Application\Workspace\Project\View\ProjectConflicts;
use App\Application\Workspace\Project\View\ProjectPage;
use App\Application\Workspace\Project\View\ProjectView;
use App\Application\Workspace\Workspace\View\WorkspaceView;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Workspace\Project\Command\RegisterProject;
use App\Domain\Workspace\Project\Project;
use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\ProjectMetadata;
use App\Domain\Workspace\Project\ProjectSources;
use App\Domain\Workspace\Project\Query\GetProjectById;
use App\Domain\Workspace\Project\Query\ListProjects;
use App\Domain\Workspace\Project\Query\PreviewProjectConflicts;
use App\Domain\Workspace\Project\SourceDescriptor;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;
use Tests\Support\AccessClock;

/**
 * Protects scoped Project dispatch and the public response contracts
 */
final class ProjectActionsTest extends TestCase
{
    private DateTimeImmutable $at;
    private WorkspaceId $workspace;
    private ProjectId $project;
    private User $actor;
    private RoleId $managedRole;
    private JSendResponseFactory $responses;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-10-20T12:00:00.123456Z');
        $this->workspace = WorkspaceId::generate();
        $this->project = ProjectId::generate();
        $this->managedRole = RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID);
        $email = EmailAddress::fromString('project-admin@example.test');
        $this->actor = User::invite(UserId::generate(), $email, $this->at);
        $this->actor->activate(
            PasswordHash::fromString('$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
            $this->at
        );
        $this->actor->assignRole($this->managedRole, $this->at);
        $this->responses = new JSendResponseFactory(new ResponseFactory(), new StreamFactory());
    }

    /**
     * Returns only a private, unreserved reference after charging the authenticated actor
     */
    public function testReferenceDoesNotConveyReservationOrCreationAuthority(): void
    {
        $database = $this->createMock(Connection::class);
        $database->expects(self::once())->method('transactional')
            ->willReturnCallback(static fn (Closure $consume): mixed => $consume());
        $database->expects(self::exactly(2))->method('fetchOne')->willReturnCallback(
            function (string $sql, array $parameters): int {
                if (str_starts_with($sql, 'INSERT')) {
                    self::assertSame([$this->actor->getId()->toString(), $this->at->getTimestamp()], $parameters);
                } else {
                    self::assertSame([$this->actor->getId()->toString()], $parameters);
                }

                return 1;
            }
        );

        $response = $this->referenceAction($database)->handle($this->request());

        self::assertSame(200, $response->getStatusCode());
        $data = $this->data($response);
        self::assertSame(['reference', 'reserved'], array_keys($data));
        self::assertIsString($data['reference']);
        self::assertMatchesRegularExpression('/\\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\\z/', $data['reference']);
        self::assertFalse($data['reserved']);
    }

    /**
     * Does not charge an attempt or issue a reference for a different Workspace
     */
    public function testReferenceAdmissionPrecedesQuotaConsumption(): void
    {
        $database = $this->createMock(Connection::class);
        $database->expects(self::never())->method('transactional');

        $this->expectException(PermissionDenied::class);
        $this->referenceAction($database)->handle($this->request(WorkspaceId::generate()));
    }

    /**
     * Uses real reference policy and response mapping with isolated authority and storage
     */
    private function referenceAction(Connection $database): GenerateExceptionalReferenceAction
    {
        $queries = $this->queries(static fn (Query $query): never => self::fail('Unexpected query.'));
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('getById')->with($this->actor->getId())->willReturn($this->actor);
        $roles = $this->createMock(RoleRepository::class);
        $role = Role::defineManaged($this->managedRole, RoleName::fromString('ROLE_SUPER_ADMIN'), [], $this->at);
        $roles->expects(self::once())->method('getById')->with(self::equalTo($this->managedRole))->willReturn($role);

        return new GenerateExceptionalReferenceAction(
            new ExceptionalReferences($database, new AccessClock($this->at)),
            $queries,
            $users,
            $roles,
            new GenerateExceptionalReferenceResponder($this->responses)
        );
    }

    /**
     * Uses a generated command identity in the committed response and trusts only the authenticated actor
     */
    public function testRegistrationReturnsTheDispatchedIdentity(): void
    {
        $commands = $this->createMock(CommandBus::class);
        $dispatched = null;
        $commands->expects(self::once())->method('execute')->willReturnCallback(
            function (RegisterProject $command) use (&$dispatched): void {
                self::assertTrue($this->workspace->equals($command->workspaceId));
                self::assertTrue($this->actor->getId()->equals($command->actorId));
                self::assertSame('project-alpha', $command->metadata->slug);
                self::assertSame([], $command->sources->all());
                $dispatched = $command->projectId->toString();
            }
        );
        $queries = $this->queries(static fn (Query $query): never => self::fail('Unexpected query.'));
        $admission = $this->admission($queries);
        $action = new RegisterProjectAction($commands, $admission, new RegisterProjectResponder($this->responses));
        $request = $this->request()->withAttribute(JsonBody::class, [
            'display_name' => 'Project Alpha', 'slug' => 'project-alpha', 'actor_id' => UserId::generate()->toString()
        ]);

        $response = $action->handle($request);

        self::assertNotNull($dispatched);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['project_id' => $dispatched], $this->data($response));
    }

    /**
     * Denies registration outside the currently admitted workspace without dispatching the command
     */
    public function testRegistrationDoesNotDispatchForOtherWorkspace(): void
    {
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('execute');
        $queries = $this->queries(static fn (Query $query): never => self::fail('Unexpected query.'));
        $action = new RegisterProjectAction(
            $commands,
            $this->admission($queries),
            new RegisterProjectResponder($this->responses)
        );
        $request = $this->request(WorkspaceId::generate())->withAttribute(JsonBody::class, [
            'display_name' => 'Project Alpha', 'slug' => 'project-alpha'
        ]);

        $this->expectException(PermissionDenied::class);
        $action->handle($request);
    }

    /**
     * Returns a scoped current view with canonical source evidence, not physical checkout details
     */
    public function testDetailReturnsSafeProjectFacts(): void
    {
        $queries = $this->queries(function (Query $query): ProjectView {
            self::assertInstanceOf(GetProjectById::class, $query);
            self::assertTrue($this->workspace->equals($query->workspaceId));
            self::assertTrue($this->project->equals($query->projectId));

            return $this->view();
        });
        $action = new GetProjectAction($queries, $this->admission($queries), new GetProjectResponder($this->responses));

        $response = $action->handle($this->request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'workspace_id'       => $this->workspace->toString(),
            'project_id'         => $this->project->toString(),
            'display_name'       => 'Project Alpha',
            'slug'               => 'project-alpha',
            'lifecycle'          => 'active',
            'primary_source'     => [
                'type' => 'github_repository_v1', 'authority' => 'github.com', 'owner' => 'team', 'repository' => 'app'
            ],
            'equivalent_sources' => [[
                'type'       => 'github_repository_v1',
                'authority'  => 'github.com',
                'owner'      => 'team',
                'repository' => 'mirror'
            ]],
            'metadata_revision'  => 1,
            'source_revision'    => 1,
            'lifecycle_revision' => 1,
            'actor_type'         => 'user',
            'created_by'         => $this->actor->getId()->toString(),
            'updated_by'         => $this->actor->getId()->toString(),
            'created_at'         => '2026-10-20T12:00:00.123456+00:00',
            'updated_at'         => '2026-10-20T12:00:00.123456+00:00'
        ], $this->data($response));
    }

    /**
     * Keeps pagination defaults and a truthful page continuation instead of inferring totals
     */
    public function testListPreservesPaginationAndReturnedPage(): void
    {
        $queries = $this->queries(function (Query $query): ProjectPage {
            self::assertInstanceOf(ListProjects::class, $query);
            self::assertTrue($this->workspace->equals($query->workspaceId));
            self::assertSame([0, 25], [$query->offset, $query->limit]);

            return new ProjectPage([$this->view()], true, 0, 25);
        });
        $action = new ListProjectsAction(
            $queries,
            $this->admission($queries),
            new ListProjectsResponder($this->responses)
        );

        $data = $this->data($action->handle($this->request()->withAttribute(QueryString::class, [])));

        self::assertSame(['items', 'has_more', 'offset', 'limit'], array_keys($data));
        self::assertSame([true, 0, 25], [$data['has_more'], $data['offset'], $data['limit']]);
        self::assertIsArray($data['items']);
        self::assertCount(1, $data['items']);
    }

    /**
     * Reveals only the advisory conflict kinds, never another Project's identity
     */
    public function testConflictPreviewDoesNotRevealAnotherProject(): void
    {
        $queries = $this->queries(function (Query $query): ProjectConflicts {
            self::assertInstanceOf(PreviewProjectConflicts::class, $query);
            self::assertTrue($this->workspace->equals($query->workspaceId));
            self::assertSame('project-alpha', $query->metadata->slug);

            return new ProjectConflicts(false, true);
        });
        $action = new PreviewProjectConflictsAction(
            $queries,
            $this->admission($queries),
            new PreviewProjectConflictsResponder($this->responses)
        );
        $request = $this->request()->withAttribute(JsonBody::class, [
            'display_name' => 'Project Alpha', 'slug' => 'project-alpha'
        ]);

        self::assertSame(['conflicts' => ['source']], $this->data($action->handle($request)));
    }

    /**
     * Supplies the initial scope and substitutes the queried application boundary
     *
     * @param callable(Query): mixed $projectQuery
     */
    private function queries(callable $projectQuery): QueryBus
    {
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturnCallback(function (Query $query) use ($projectQuery): mixed {
            if ($query instanceof FindInitialWorkspace) {
                return new WorkspaceView($this->workspace, $this->at);
            }

            return $projectQuery($query);
        });

        return $queries;
    }

    /**
     * Uses the actual scope check with an authoritative current user and role result
     */
    private function admission(QueryBus $queries): ProjectWorkspaceAdmission
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('getById')->with($this->actor->getId())->willReturn($this->actor);
        $roles = $this->createMock(RoleRepository::class);
        $role = Role::defineManaged($this->managedRole, RoleName::fromString('ROLE_SUPER_ADMIN'), [], $this->at);
        $roles->expects(self::once())->method('getById')->with(self::equalTo($this->managedRole))->willReturn($role);

        return new ProjectWorkspaceAdmission($queries, $users, $roles);
    }

    /**
     * Builds an already authenticated request with separate route identities
     */
    private function request(?WorkspaceId $workspace = null): ServerRequestInterface
    {
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturnMap([
            ['workspace_id', null, ($workspace ?? $this->workspace)->toString()],
            ['project_id', null, $this->project->toString()]
        ]);
        $authentication = new AuthenticationContext(
            $this->actor->getId(),
            RefreshSessionId::generate(),
            $this->actor->getAuthenticationVersion()
        );

        return new ServerRequestFactory()->createServerRequest('GET', '/projects')
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class))
            ->withAttribute(AuthenticationContext::class, $authentication);
    }

    /**
     * Supplies safe Project facts with explicit primary and equivalent source evidence
     */
    private function view(): ProjectView
    {
        $source = static fn (string $name): SourceDescriptor => SourceDescriptor::fromInput([
            'type' => 'github_repository_v1', 'locator' => 'https://github.com/team/'.$name
        ]);

        return ProjectView::fromProject(Project::register(
            $this->project,
            $this->workspace,
            new ProjectMetadata('Project Alpha', 'project-alpha'),
            new ProjectSources($source('app'), [$source('mirror')]),
            $this->actor->getId(),
            $this->at
        ));
    }

    /**
     * Checks that the success envelope is private and returns only its public data fields
     *
     * @return array<string, mixed>
     */
    private function data(ResponseInterface $response): array
    {
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertFalse($response->hasHeader('Set-Cookie'));
        $body = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('success', $body['status']);
        self::assertIsArray($body['data']);

        return $body['data'];
    }
}
