<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\V1\Projects\ProjectInput;
use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\ProjectInputRejected;
use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;

/**
 * Protects canonical Project route input and rejects unverified authority
 */
final class ProjectInputTest extends TestCase
{
    /**
     * Reads distinct Workspace and Project identities from route arguments rather than query aliases
     */
    public function testRouteIdentities(): void
    {
        $workspace = WorkspaceId::generate();
        $project = ProjectId::generate();
        $request = $this->request($workspace->toString(), $project->toString());

        self::assertTrue($workspace->equals(ProjectInput::workspace($request)));
        self::assertTrue($project->equals(ProjectInput::project($request)));
    }

    /**
     * Rejects a textual UUID alias at the transport boundary
     */
    public function testNoncanonicalProjectIdentity(): void
    {
        $request = $this->request(WorkspaceId::generate()->toString(), '{'.ProjectId::generate()->toString().'}');

        $this->expectException(ProjectInputRejected::class);
        ProjectInput::project($request);
    }

    /**
     * Rejects authority claims supplied only in the request body
     */
    public function testBodyCannotSupplyAuthentication(): void
    {
        $request = $this->request()->withParsedBody(['user_id' => UserId::generate()->toString()]);

        $this->expectException(AuthenticationRequired::class);
        ProjectInput::authentication($request);
    }

    /**
     * Preserves the exact authentication context attached by middleware
     */
    public function testMiddlewareSuppliesAuthentication(): void
    {
        $context = new AuthenticationContext(UserId::generate(), RefreshSessionId::generate(), 3);
        $request = $this->request()->withAttribute(AuthenticationContext::class, $context);

        self::assertSame($context, ProjectInput::authentication($request));
    }

    /**
     * Keeps the primary and explicitly asserted equivalent sources distinct while normalizing each locator
     */
    public function testSourceObjectsMapToCanonicalEvidence(): void
    {
        $sources = ProjectInput::sources([
            'primary_source'     => (object) [
                'type' => 'github_repository_v1', 'locator' => 'https://github.com/TEAM/Site.git'
            ],
            'equivalent_sources' => [(object) [
                'type' => 'git_network_v1', 'locator' => 'https://mirror.test/Team/Site.git'
            ]]
        ]);

        self::assertSame(['github_repository_v1', 'git_network_v1'], array_map(
            static fn ($source): string => (string) $source->toArray()['type'],
            $sources->all()
        ));
        self::assertSame('site', $sources->all()[0]->toArray()['repository']);
        self::assertSame('mirror.test', $sources->all()[1]->toArray()['host']);
    }

    /**
     * Rejects ambiguous JSON collection and item shapes before interpreting their values
     *
     * @param array<string, mixed> $input
     */
    #[DataProvider('malformedSources')]
    public function testRejectsMalformedSources(array $input): void
    {
        $this->expectException(ProjectInputRejected::class);
        ProjectInput::sources($input);
    }

    /**
     * Supplies shapes not admitted by the request contract
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedSources(): iterable
    {
        yield 'primary list' => [['primary_source' => []]];
        yield 'equivalent object' => [['equivalent_sources' => (object) []]];
        yield 'named equivalent' => [['equivalent_sources' => ['mirror' => (object) []]]];
        yield 'excess equivalents' => [['equivalent_sources' => array_fill(0, 8, (object) [])]];
        yield 'scalar equivalent' => [['equivalent_sources' => ['mirror']]];
    }

    /**
     * Builds a request with route values from the HTTP adapter boundary
     */
    private function request(?string $workspace = null, ?string $project = null): ServerRequestInterface
    {
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturnMap([
            ['workspace_id', null, $workspace], ['project_id', null, $project]
        ]);
        $uri = '/projects?workspace_id=ignored&project_id=ignored';

        return new ServerRequestFactory()->createServerRequest('GET', $uri)
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
    }
}
