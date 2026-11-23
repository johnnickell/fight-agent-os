<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\Workspace\Project\PostgresProjectRegistrationAudit;
use App\Adapter\Persistence\Workspace\Workspace\PostgresWorkspaceBootstrapAudit;
use App\Domain\Workspace\Project\Project;
use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\ProjectMetadata;
use App\Domain\Workspace\Project\ProjectSources;
use App\Domain\Workspace\Project\SourceDescriptor;
use App\Domain\Workspace\Workspace\Workspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects durable audit attribution and revision evidence for created Workspace objects
 */
final class WorkspaceAuditTest extends TestCase
{
    /**
     * Records the initial Workspace without a fabricated Project subject
     */
    public function testAttributesBootstrapToTheInstallationOperator(): void
    {
        $workspace = new Workspace(WorkspaceId::generate(), new DateTimeImmutable('2026-10-20T12:00:00.123456Z'));
        $operator = UserId::generate();
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('insert')->with('workspace_audit', self::callback(
            static function (array $row) use ($workspace, $operator): bool {
                self::assertNotEmpty($row['id']);
                unset($row['id']);
                self::assertEquals([
                    'workspace_id'         => $workspace->id->toString(),
                    'project_id'           => null,
                    'actor_type'           => 'installation_operator',
                    'actor_id'             => $operator->toString(),
                    'action'               => 'workspace_bootstrapped',
                    'predecessor_revision' => 0,
                    'result_revision'      => 1,
                    'result_code'          => 'created',
                    'created_at'           => '2026-10-20 12:00:00.123456+00:00'
                ], $row);

                return true;
            }
        ));

        (new PostgresWorkspaceBootstrapAudit($connection))->record($workspace, $operator);
    }

    /**
     * Attributes the registration to the Project creator and retains both aggregate identifiers
     */
    public function testRecordsRegistrationWithProjectAndWorkspace(): void
    {
        $project = $this->project();
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('insert')->with('workspace_audit', self::callback(
            static function (array $row) use ($project): bool {
                self::assertNotEmpty($row['id']);
                unset($row['id']);
                self::assertEquals([
                    'workspace_id'         => $project->workspaceId->toString(),
                    'project_id'           => $project->id->toString(),
                    'actor_type'           => 'user',
                    'actor_id'             => $project->createdBy->toString(),
                    'action'               => 'project_registered',
                    'predecessor_revision' => 0,
                    'result_revision'      => 1,
                    'result_code'          => 'created',
                    'created_at'           => '2026-10-20 12:00:00.123456+00:00'
                ], $row);

                return true;
            }
        ));

        (new PostgresProjectRegistrationAudit($connection))->record($project);
    }

    /**
     * Never inserts audit evidence independently of the business transaction
     */
    #[DataProvider('auditActions')]
    public function testDetachedAuditIsRefused(bool $projectAudit, string $reason): void
    {
        $project = $this->project();
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::never())->method('insert');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($reason);

        if ($projectAudit) {
            (new PostgresProjectRegistrationAudit($connection))->record($project);
        } else {
            (new PostgresWorkspaceBootstrapAudit($connection))->record(
                new Workspace($project->workspaceId, $project->createdAt),
                $project->createdBy
            );
        }
    }

    /**
     * Supplies both audit admission boundaries
     *
     * @return iterable<string, array{bool, string}>
     */
    public static function auditActions(): iterable
    {
        yield 'registration' => [true, 'Project audit requires an enclosing transaction.'];
        yield 'bootstrap' => [false, 'Workspace audit requires an enclosing transaction.'];
    }

    /**
     * Supplies independently generated identities and a fixed registration instant
     */
    private function project(): Project
    {
        return Project::register(
            ProjectId::generate(),
            WorkspaceId::generate(),
            new ProjectMetadata('Project Alpha', 'project-alpha'),
            new ProjectSources(SourceDescriptor::fromInput([
                'type' => 'github_repository_v1', 'locator' => 'https://github.com/team/source'
            ])),
            UserId::generate(),
            new DateTimeImmutable('2026-10-20T12:00:00.123456Z')
        );
    }
}
