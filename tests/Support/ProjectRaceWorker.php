<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Console\BootstrapWorkspaceCommand;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Domain\Workspace\Project\Command\RegisterProject;
use App\Domain\Workspace\Workspace\Command\BootstrapWorkspace;
use App\Domain\Workspace\Workspace\InitialWorkspaceAlreadyExists;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Slim\Psr7\Factory\ServerRequestFactory;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

/**
 * Class ProjectRaceWorker
 *
 * Exercises independent real HTTP/command connections behind explicit bounded barriers
 */
final class ProjectRaceWorker
{
    /**
     * Runs guarded fixture work without publishing private diagnostics
     */
    public static function run(): void
    {
        try {
            $input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
            $url = (string) getenv('TEST_DATABASE_URL');
            $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
            $target = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
            putenv('DATABASE_URL='.$url);
            $app = WorkspaceTestClock::application(new DateTimeImmutable($input['clock']));
            $container = $app->getContainer();
            $connection = $container->get(Connection::class);
            $guard->assertConnected($connection, $target);
            $connection->executeStatement("SET statement_timeout = '15s'");
            echo $connection->fetchOne('SELECT pg_backend_pid()')."\n";
            flush();
            if ($input['mode'] === 'bootstrap_console') {
                $queryBus = $container->get(QueryBus::class);
                $container->set(QueryBus::class, static fn() => new class ($queryBus) implements QueryBus {
                    /**
                     * Constructs the competing Console read barrier
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
                            ProjectRaceWorker::barrier('ready');
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
            } else {
                if (($input['barrier'] ?? '') === 'admitted') {
                    $commandBus = $container->get(CommandBus::class);
                    $container->set(CommandBus::class, static fn() => new class ($commandBus) implements CommandBus {
                        /**
                         * Constructs the after-admission dispatch barrier
                         */
                        public function __construct(private readonly CommandBus $commandBus)
                        {
                        }

                        /**
                         * @inheritDoc
                         */
                        public function execute(Command $command): void
                        {
                            if ($command instanceof RegisterProject) {
                                ProjectRaceWorker::barrier('admitted');
                            }
                            $this->commandBus->execute($command);
                        }

                        /**
                         * @inheritDoc
                         */
                        public function dispatch(CommandMessage $commandMessage): void
                        {
                            $this->commandBus->dispatch($commandMessage);
                        }
                    });
                } else {
                    self::barrier('ready');
                }
                $suffix = $input['mode'] === 'reference' ? '/references' : '/projects';
                $request = (new ServerRequestFactory())->createServerRequest(
                    'POST',
                    'https://localhost/api/v1/workspaces/'.$input['workspace'].$suffix
                )
                    ->withHeader('Authorization', 'Bearer '.$input['token']);
                if ($input['mode'] !== 'reference') {
                    $request = $request->withHeader('Content-Type', 'application/json');
                    $request->getBody()->write(json_encode($input['body'], JSON_THROW_ON_ERROR));
                }
                $response = $app->handle($request);
                echo json_encode([
                    'status'      => $response->getStatusCode(),
                    'retry_after' => $response->getHeaderLine('Retry-After'),
                    'body'        => json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)
                ], JSON_THROW_ON_ERROR)."\n";
            }
        } catch (Throwable) {
            echo "failed\n";
            exit(1);
        }
    }

    /**
     * Enforces one explicit release with a finite protocol timeout
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
