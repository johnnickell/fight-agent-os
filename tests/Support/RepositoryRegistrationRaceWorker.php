<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Console\BootstrapWorkspaceCommand;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Application\Workspace\RegisterRepository;
use App\Application\Workspace\RegistrationFences;
use App\Domain\Workspace\RegistrationConflict;
use App\Domain\Workspace\RegistrationIntent;
use App\Domain\Workspace\RegistrationMetadata;
use App\Domain\Workspace\RegistrationSources;
use App\Domain\Workspace\SourceDescriptor;
use App\Domain\Workspace\Workspace\Command\BootstrapWorkspace;
use App\Domain\Workspace\Workspace\InitialWorkspaceAlreadyExists;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Slim\Psr7\Factory\ServerRequestFactory;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

/**
 * Class RepositoryRegistrationRaceWorker
 *
 * Exercises real independent connections with explicit transaction and authority barriers
 */
final class RepositoryRegistrationRaceWorker
{
    /**
     * Runs one bounded guarded operation without publishing private diagnostics
     */
    public static function run(): void
    {
        try {
            $input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
            $url = (string) getenv('TEST_DATABASE_URL');
            $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
            $target = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
            $connection = DriverManager::getConnection((new DsnParser([
                'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
            ]))->parse($url));
            $guard->assertConnected($connection, $target);
            $connection->executeStatement("SET statement_timeout = '15s'");
            putenv('DATABASE_URL='.$url);
            $container = require dirname(__DIR__, 2).'/config/services.php';
            $container->set(Connection::class, static fn(): Connection => $connection);
            WorkspaceTestClock::configure($container, new DateTimeImmutable($input['clock']));
            echo $connection->fetchOne('SELECT pg_backend_pid()')."\n";
            flush();
            if ($input['mode'] === 'reference') {
                $app = WorkspaceTestClock::application(new DateTimeImmutable($input['clock']));
                self::barrier('ready');
                $request = (new ServerRequestFactory())->createServerRequest(
                    'POST',
                    'https://localhost/api/v1/workspaces/'.$input['workspace'].'/references'
                )->withHeader('Authorization', 'Bearer '.$input['token']);
                $response = $app->handle($request);
                echo json_encode([
                    'status'      => $response->getStatusCode(),
                    'retry_after' => $response->getHeaderLine('Retry-After')
                ], JSON_THROW_ON_ERROR)."\n";
            } elseif ($input['mode'] === 'bootstrap_console') {
                $queryBus = $container->get(QueryBus::class);
                $container->set(QueryBus::class, static fn() => new class ($queryBus) implements QueryBus {
                    /**
                     * Constructs the real-read barrier for competing Console invocations
                     */
                    public function __construct(private readonly QueryBus $queryBus)
                    {
                    }

                    /**
                     * @inheritDoc
                     */
                    public function fetch(Query $query): mixed
                    {
                        $result = $this->queryBus->fetch($query);
                        if ($query instanceof FindInitialWorkspace) {
                            RepositoryRegistrationRaceWorker::barrier('ready');
                        }

                        return $result;
                    }

                    /**
                     * @inheritDoc
                     */
                    public function dispatch(QueryMessage $queryMessage): mixed
                    {
                        return $this->queryBus->dispatch($queryMessage);
                    }
                });
                $console = new CommandTester($container->get(BootstrapWorkspaceCommand::class));
                $exit = $console->execute([
                    '--internal' => true, '--confirm-bootstrap' => true, '--operator' => $input['actor']
                ]);
                $data = $exit === 0 ? json_decode($console->getDisplay(), true, flags: JSON_THROW_ON_ERROR) : [];
                echo json_encode([
                    'status'       => $exit === 0 ? 'bootstrapped' : 'conflict',
                    'workspace_id' => $data['workspace_id'] ?? null
                ], JSON_THROW_ON_ERROR)."\n";
            } elseif ($input['mode'] === 'bootstrap') {
                self::barrier('ready');
                $workspaceId = WorkspaceId::generate();
                try {
                    $container->get(CommandBus::class)->execute(new BootstrapWorkspace(
                        $workspaceId,
                        UserId::fromString($input['actor'])
                    ));
                    $status = 'bootstrapped';
                } catch (InitialWorkspaceAlreadyExists) {
                    $status = 'conflict';
                }
                echo json_encode([
                    'status' => $status, 'workspace_id' => $workspaceId->toString()
                ], JSON_THROW_ON_ERROR)."\n";
            } elseif ($input['mode'] === 'revoke') {
                if (($input['subject'] ?? 'permission') === 'assignment') {
                    $users = $container->get(UserRepository::class);
                    $user = $users->getById(UserId::fromString($input['actor']));
                    if (!$user instanceof User) {
                        throw new \RuntimeException('Missing fixture actor.');
                    }
                    $replacement = clone $user;
                    $replacement->removeRole(RoleId::fromString($input['role']), new DateTimeImmutable());
                    $write = static fn(): bool => $users->replaceRoleAssignments($user, $replacement);
                } elseif (($input['subject'] ?? 'permission') === 'session') {
                    $sessions = $container->get(RefreshSessionRepository::class);
                    $session = $sessions->getById(RefreshSessionId::fromString($input['session']));
                    $write = static fn(): bool => $sessions->replace($session, $session->revoke());
                } else {
                    $roles = $container->get(RoleRepository::class);
                    $role = $roles->getById(RoleId::fromString($input['role']));
                    $permission = $container->get(PermissionRepository::class)->getByName(
                        PermissionName::fromString($input['permission'])
                    );
                    $replacement = $role->revokePermissionFromCustom($permission->getId(), new DateTimeImmutable());
                    $write = static fn(): bool => $roles->replace($role, $replacement);
                }
                $changed = $container->get(TransactionalUnitOfWork::class)->commitTransactional(static function () use (
                    $write
                ): bool {
                    self::barrier('ready');

                    return $write();
                });
                echo json_encode(['status' => $changed ? 'revoked' : 'stale'], JSON_THROW_ON_ERROR)."\n";
            } else {
                $real = $container->get(RegistrationFences::class);
                $fences = new class ($real, $input['barrier'] ?? 'before') implements RegistrationFences {
                    /**
                     * Constructs the explicit test synchronization boundary
                     */
                    public function __construct(
                        private readonly RegistrationFences $real,
                        private readonly string $position
                    ) {
                    }

                    /**
                     * @inheritDoc
                     */
                    public function holdAuthority(AuthenticationContext $authentication): void
                    {
                        if ($this->position === 'before') {
                            RepositoryRegistrationRaceWorker::barrier('ready');
                        }
                        $this->real->holdAuthority($authentication);
                        if ($this->position === 'after') {
                            RepositoryRegistrationRaceWorker::barrier('held');
                        }
                    }
                };
                $container->set(RegistrationFences::class, static fn(): RegistrationFences => $fences);
                $body = $input['body'];
                $primary = isset($body['primary_source']) ? SourceDescriptor::fromInput($body['primary_source']) : null;
                $equivalent = array_values(array_map(
                    SourceDescriptor::fromInput(...),
                    $body['equivalent_sources'] ?? []
                ));
                try {
                    $view = $container->get(RegisterRepository::class)->register(new AuthenticationContext(
                        UserId::fromString($input['actor']),
                        RefreshSessionId::fromString($input['session']),
                        $input['version']
                    ), WorkspaceId::fromString($input['workspace']), new RegistrationIntent(
                        new RegistrationMetadata($body['display_name'], $body['slug']),
                        new RegistrationSources($primary, $equivalent),
                        $body['idempotency_key']
                    ));
                    echo json_encode([
                        'status' => 'registered', 'repository_id' => $view->data['repository_id']
                    ], JSON_THROW_ON_ERROR)."\n";
                } catch (RegistrationConflict $conflict) {
                    echo json_encode(['status' => 'conflict', 'reason' => $conflict->reason], JSON_THROW_ON_ERROR)."\n";
                } catch (\Fight\Common\Application\Error\Exception\PermissionDenied) {
                    echo json_encode(['status' => 'denied'], JSON_THROW_ON_ERROR)."\n";
                }
            }
        } catch (Throwable) {
            echo "failed\n";
            exit(1);
        }
    }

    /**
     * Enforces a bounded protocol barrier until the parent explicitly releases this worker
     */
    public static function barrier(string $state): void
    {
        echo $state."\n";
        flush();
        stream_set_timeout(STDIN, 15);
        if (trim((string) fgets(STDIN)) !== 'go') {
            throw new \RuntimeException('Race barrier was not released.');
        }
    }
}
