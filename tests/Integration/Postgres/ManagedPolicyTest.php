<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Console\ManagedPolicyConsole;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Locking\AuthorizationReferenceFences;
use App\Adapter\Persistence\Repository\PostgresAgentRepository;
use App\Adapter\Persistence\Repository\PostgresFeatureRepository;
use App\Adapter\Persistence\Repository\PostgresPermissionRepository;
use App\Adapter\Persistence\Repository\PostgresRoleRepository;
use App\Adapter\Persistence\Repository\PostgresUserRepository;
use App\Domain\Authorization\InstallationManagedPolicy;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Fight\AccessControl\Application\AccessControl\ManagedPolicy\CommandHandler\ReconcileManagedPolicyHandler;
use Fight\AccessControl\Application\AccessControl\ManagedPolicy\QueryHandler\PreviewManagedPolicyHandler;
use Fight\AccessControl\Application\AccessControl\ManagedPolicy\Service\ManagedPolicyPlanner;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\Agent\Agent;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentCredentialId;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentId;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentName;
use Fight\AccessControl\Domain\AccessControl\Feature\Feature;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureId;
use Fight\AccessControl\Domain\AccessControl\Feature\FeatureName;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Event\ManagedPolicyReconciled;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Messaging\Event\CommandFailedEvent;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Qualifies installation policy operations against the owned PostgreSQL adapters
 */
final class ManagedPolicyTest extends TestCase
{
    private Connection $connection;
    private PostgresPermissionRepository $permissions;
    private PostgresRoleRepository $roles;
    private PostgresUserRepository $users;
    private PostgresAgentRepository $agents;
    private DoctrineTransactionalUnitOfWork $unitOfWork;
    /** @var list<Event> */
    private array $events = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('TRUNCATE users, agents, roles, permissions CASCADE');
        $fences = new AuthorizationReferenceFences($this->connection);
        $this->permissions = new PostgresPermissionRepository($this->connection, $fences);
        $this->roles = new PostgresRoleRepository($this->connection, $fences);
        $this->users = new PostgresUserRepository(
            $this->connection,
            $fences,
            new AuthenticationAuthorityFences($this->connection)
        );
        $this->agents = new PostgresAgentRepository($this->connection, $fences);
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $this->unitOfWork = new DoctrineTransactionalUnitOfWork(new EntityManager($this->connection, $configuration));
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->connection->close();
    }

    /**
     * Previews without writes and reconciles exact authority with post-commit events and stable repeated state
     */
    public function testFreshPreviewApplyAndIdempotency(): void
    {
        $console = $this->console();
        // PostgreSQL enforces the query contract rather than merely comparing final rows.
        $this->connection->beginTransaction();
        $this->connection->executeStatement('SET TRANSACTION READ ONLY');
        $preview = $this->runConsole($console, 'preview');
        $this->connection->commit();
        self::assertSame([], $this->observedEvents());
        self::assertSame('0', (string) $this->connection->fetchOne('SELECT count(*) FROM permissions'));
        self::assertSame(array_fill(0, 25, 'CREATE'), array_column($preview['plan']['permissions'], 'action'));
        self::assertSame(['CREATE', 'CREATE'], array_column($preview['plan']['roles'], 'action'));
        $this->runConsole($console, 'apply');
        self::assertCount(1, $this->events);
        self::assertInstanceOf(ManagedPolicyReconciled::class, $this->events[0]);
        $this->assertExactPolicy();
        $state = $this->snapshot();
        $preview = $this->runConsole($console, 'preview');
        self::assertSame(array_fill(0, 25, 'UNCHANGED'), array_column($preview['plan']['permissions'], 'action'));
        self::assertSame(['UNCHANGED', 'UNCHANGED'], array_column($preview['plan']['roles'], 'action'));
        $this->runConsole($console, 'apply');
        self::assertSame($state, $this->snapshot());
        // The tagged handler emits a completed reconciliation event even on a no-op.
        self::assertCount(2, $this->events);
    }

    /**
     * Repairs supported managed drift while preserving unrelated custom authority
     */
    public function testDriftRepairAndObsoleteRemoval(): void
    {
        $console = $this->console();
        $this->runConsole($console, 'apply');
        $custom = Permission::define(PermissionId::generate(), PermissionName::fromString('CUSTOM_READ'), $this->now());
        $obsolete = $this->obsoletePermission();
        $customRole = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_CUSTOM'),
            [$custom->getId()],
            $this->now()
        );
        $oldRole = Role::defineManaged(
            RoleId::generate(),
            RoleName::fromString('ROLE_OLD'),
            [$obsolete->getId()],
            $this->now()
        );
        $this->unitOfWork->commitTransactional(function () use ($custom, $customRole, $obsolete, $oldRole): void {
            $this->permissions->add($custom);
            $this->permissions->add($obsolete);
            $this->roles->add($customRole);
            $this->roles->add($oldRole);
            $swagger = $this->permissions->getByName(PermissionName::fromString('READ_SWAGGER'));
            self::assertTrue($this->permissions->replace($swagger, $swagger->reconcileManaged(
                PermissionName::fromString('OLD_SWAGGER'),
                PermissionTier::ADMIN_SAFE,
                $this->now()->modify('+1 second')
            )));
            $user = $this->roles->getById(RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID));
            self::assertTrue($this->roles->replace($user, $user->reconcileManaged(
                RoleName::fromString('ROLE_OLD_USER'),
                [],
                $this->now()->modify('+1 second')
            )));
        });
        $plan = $this->runConsole($console, 'preview')['plan'];
        self::assertSame('RECONCILE', array_column($plan['permissions'], 'action', 'name')['READ_SWAGGER']);
        self::assertSame('RECONCILE', array_column($plan['roles'], 'action', 'name')['ROLE_USER']);
        self::assertSame('REMOVE', array_column($plan['permissions'], 'action', 'name')['OBSOLETE']);
        self::assertSame('REMOVE', array_column($plan['roles'], 'action', 'name')['ROLE_OLD']);
        $this->runConsole($console, 'apply');
        $this->assertExactPolicy();
        self::assertNull($this->permissions->getById($obsolete->getId()));
        self::assertNull($this->roles->getById($oldRole->getId()));
        self::assertEquals($custom, $this->permissions->getById($custom->getId()));
        $preserved = $this->roles->getById($customRole->getId());
        self::assertFalse($preserved->isManaged());
        self::assertTrue($preserved->getName()->equals($customRole->getName()));
        self::assertEquals($customRole->getUpdatedAt(), $preserved->getUpdatedAt());
        self::assertEquals($customRole->getPermissionIds(), $preserved->getPermissionIds());
    }

    /**
     * Rejects unmanaged name and identity collisions without converting or partially creating authority
     */
    #[DataProvider('collisions')]
    public function testUnmanagedConflict(string $collision): void
    {
        $policy = InstallationManagedPolicy::create();
        $definition = $policy->getPermissions()[0];
        $this->permissions->add(Permission::define(
            $collision === 'identity' ? $definition->getId() : PermissionId::generate(),
            PermissionName::fromString($collision === 'identity' ? 'CUSTOM_READ' : 'VIEW_DASHBOARD'),
            $this->now()
        ));
        $state = $this->snapshot();
        $this->runConsole($this->console(), 'preview', 1);
        self::assertSame([], $this->observedEvents());
        $this->runConsole($this->console(), 'apply', 1);
        self::assertSame($state, $this->snapshot());
        self::assertInstanceOf(CommandFailedEvent::class, $this->events[0]);
    }

    /**
     * Preserves obsolete authority when code or persisted assignments still reference it
     */
    #[DataProvider('references')]
    public function testRemovalFences(string $reference): void
    {
        $permission = $this->obsoletePermission();
        $policy = InstallationManagedPolicy::create();
        $role = Role::defineManaged(RoleId::generate(), RoleName::fromString('ROLE_OLD'), [], $this->now());
        $this->unitOfWork->commitTransactional(function () use ($reference, $permission, $role): void {
            $this->permissions->add($permission);
            if ($reference === 'role') {
                $this->roles->add(Role::define(
                    RoleId::generate(),
                    RoleName::fromString('ROLE_CUSTOM'),
                    [$permission->getId()],
                    $this->now()
                ));
            }
            if ($reference === 'feature') {
                (new PostgresFeatureRepository($this->connection, new AuthorizationReferenceFences($this->connection)))
                    ->add(Feature::define(
                        FeatureId::generate(),
                        FeatureName::fromString('old-feature'),
                        $permission->getId()
                    ));
            }
            if ($reference === 'agent') {
                $agent = Agent::provision(
                    AgentId::generate(),
                    AgentName::fromString('fixture-only'),
                    AgentCredentialId::generate(),
                    'synthetic-envelope',
                    $this->now()
                );
                $this->agents->add($agent);
                self::assertTrue($this->agents->replacePermissionAssignments(
                    $agent,
                    $agent->grantPermission($permission->getId(), $this->now()->modify('+1 second'))
                ));
            }
            if ($reference === 'user') {
                $this->roles->add($role);
                $user = User::invite(
                    UserId::generate(),
                    EmailAddress::fromString('fixture@example.test'),
                    $this->now()
                );
                $user->assignRole($role->getId(), $this->now());
                $this->users->add($user);
            }
        });
        if ($reference === 'code') {
            $policy = new ManagedPolicy(
                $policy->getPermissions(),
                $policy->getRoles(),
                [...$policy->getReferencedPermissionIds(), $permission->getId()]
            );
        }
        $state = $this->snapshot();
        $this->runConsole($this->console($policy), 'apply', 1);
        self::assertSame($state, $this->snapshot());
        self::assertInstanceOf(CommandFailedEvent::class, $this->events[0]);
    }

    /**
     * Rolls back permissions when persistence fails after writes have begun
     */
    public function testInjectedWriteFailureRollsBackAndRedacts(): void
    {
        $this->connection->executeStatement("CREATE FUNCTION reject_policy_role() RETURNS trigger LANGUAGE plpgsql AS '
            BEGIN RAISE EXCEPTION ''SQL private-token fixture''; END;'");
        $this->connection->executeStatement('CREATE TRIGGER reject_policy_role BEFORE INSERT ON roles
            FOR EACH ROW EXECUTE FUNCTION reject_policy_role()');
        try {
            $this->runConsole($this->console(), 'apply', 1);
            self::assertSame('0', (string) $this->connection->fetchOne('SELECT count(*) FROM permissions'));
            self::assertSame('0', (string) $this->connection->fetchOne('SELECT count(*) FROM roles'));
            self::assertCount(1, $this->events);
            self::assertInstanceOf(CommandFailedEvent::class, $this->events[0]);
        } finally {
            $this->connection->executeStatement('DROP TRIGGER reject_policy_role ON roles');
            $this->connection->executeStatement('DROP FUNCTION reject_policy_role()');
        }
    }

    /**
     * Revalidates advisory previews and rejects a competing create after transactional preflight
     */
    public function testStalePreviewAndConcurrentPreflightConflict(): void
    {
        $this->runConsole($this->console(), 'preview');
        $other = $this->connection();
        try {
            $competitor = $this->console(null, function () use ($other): void {
                $permissions = new PostgresPermissionRepository($other, new AuthorizationReferenceFences($other));
                $permissions->add(Permission::define(
                    PermissionId::generate(),
                    PermissionName::fromString('VIEW_DASHBOARD'),
                    $this->now()
                ));
            });
            $this->runConsole($competitor, 'apply', 1);
            self::assertSame('1', (string) $other->fetchOne('SELECT count(*) FROM permissions'));
            self::assertSame('0', (string) $other->fetchOne('SELECT count(*) FROM roles'));
            // A new apply must not trust the previously successful preview either.
            $this->runConsole($this->console(), 'apply', 1);
            self::assertCount(2, $this->events);
        } finally {
            $other->close();
        }
    }

    /**
     * Fails boundedly under a competing write and succeeds only after its rollback
     */
    public function testCompetingUncommittedCreate(): void
    {
        $other = $this->connection();
        $definition = InstallationManagedPolicy::create()->getPermissions()[0];
        $otherPermissions = new PostgresPermissionRepository($other, new AuthorizationReferenceFences($other));
        try {
            $other->beginTransaction();
            $otherPermissions->add(Permission::defineManaged(
                $definition->getId(),
                $definition->getName(),
                $definition->getTier(),
                $this->now()
            ));
            $this->connection->executeStatement("SET lock_timeout = '100ms'");
            $this->runConsole($this->console(), 'apply', 1);
            self::assertSame('0', (string) $this->connection->fetchOne('SELECT count(*) FROM permissions'));
            $other->rollBack();
            $this->runConsole($this->console(), 'apply');
            $this->assertExactPolicy();
        } finally {
            $other->close();
        }
    }

    /**
     * Rejects malformed stored definitions without treating corrupt managed authority as absent
     */
    public function testMalformedStoredDefinition(): void
    {
        $this->runConsole($this->console(), 'apply');
        $this->connection->update('permissions', ['name' => 'invalid name'], ['name' => 'READ_SWAGGER']);
        $state = $this->snapshot();
        $this->runConsole($this->console(), 'preview', 1);
        $this->runConsole($this->console(), 'apply', 1);
        self::assertSame($state, $this->snapshot());
        self::assertCount(2, $this->events);
        self::assertInstanceOf(CommandFailedEvent::class, $this->events[1]);
    }

    /**
     * Keeps Swagger authority out of custom Roles and direct Agent assignments
     */
    public function testSwaggerCannotBeDelegated(): void
    {
        $this->runConsole($this->console(), 'apply');
        $swagger = $this->permissions->getByName(PermissionName::fromString('READ_SWAGGER'));
        $state = $this->snapshot();
        $this->connection->beginTransaction();
        try {
            self::assertFalse($this->agents->validatePermissionAssignments([$swagger]));
            $this->roles->add(Role::define(
                RoleId::generate(),
                RoleName::fromString('ROLE_CUSTOM'),
                [$swagger->getId()],
                $this->now()
            ));
            self::fail('A custom Role must not hold protected Swagger authority.');
        } catch (\App\Adapter\Persistence\PersistenceConflict $failure) {
            self::assertSame('Role permission membership is not authoritative.', $failure->getMessage());
        } finally {
            $this->connection->rollBack();
        }
        self::assertSame($state, $this->snapshot());
    }

    /**
     * Allows one complete winner when two reconciliation transactions planned the empty catalog
     */
    public function testCompetingReconciliations(): void
    {
        $other = $this->connection();
        try {
            $winner = $this->console(connection: $other);
            $loser = $this->console(afterPreflight: function () use ($winner): void {
                $this->runConsole($winner, 'apply');
            });
            $this->runConsole($loser, 'apply', 1);
            $this->assertExactPolicy();
            self::assertCount(2, $this->events);
            self::assertInstanceOf(ManagedPolicyReconciled::class, $this->events[0]);
            self::assertInstanceOf(CommandFailedEvent::class, $this->events[1]);
        } finally {
            $other->close();
        }
    }

    /**
     * Supplies unmanaged collision kinds
     *
     * @return iterable<array{string}>
     */
    public static function collisions(): iterable
    {
        yield ['name'];
        yield ['identity'];
    }

    /**
     * Supplies independent removal fences
     *
     * @return iterable<array{string}>
     */
    public static function references(): iterable
    {
        yield ['code'];
        yield ['role'];
        yield ['feature'];
        yield ['agent'];
        yield ['user'];
    }

    /**
     * Composes real package buses and handlers on the canonical test connection
     */
    private function console(
        ?ManagedPolicy $policy = null,
        ?Closure $afterPreflight = null,
        ?Connection $connection = null
    ): ManagedPolicyConsole {
        $connection ??= $this->connection;
        $fences = new AuthorizationReferenceFences($connection);
        $permissions = new PostgresPermissionRepository($connection, $fences);
        $roles = new PostgresRoleRepository($connection, $fences);
        $users = new PostgresUserRepository($connection, $fences, new AuthenticationAuthorityFences($connection));
        $agents = new PostgresAgentRepository($connection, $fences);
        $planner = new ManagedPolicyPlanner($permissions, $roles, $users, $agents);
        $events = $this->createStub(EventDispatcher::class);
        $events->method('trigger')->willReturnCallback(function (Event $event) use ($connection): void {
            self::assertFalse($connection->isTransactionActive(), 'Events follow commit or rollback.');
            $this->events[] = $event;
        });
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturnCallback(function () use ($afterPreflight): DateTimeImmutable {
            $afterPreflight?->__invoke();

            return $this->now();
        });
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $unitOfWork = new DoctrineTransactionalUnitOfWork(new EntityManager($connection, $configuration));
        $commands = new InMemoryCommandRouter();
        $commands->registerHandler(ReconcileManagedPolicy::class, new ReconcileManagedPolicyHandler(
            $permissions,
            $roles,
            $planner,
            $unitOfWork,
            $events,
            $clock
        ));
        $queries = new InMemoryQueryRouter();
        $queries->registerHandler(PreviewManagedPolicy::class, new PreviewManagedPolicyHandler($planner));

        return new ManagedPolicyConsole(
            $policy ?? InstallationManagedPolicy::create(),
            new RoutingQueryBus($queries),
            new RoutingCommandBus($commands)
        );
    }

    /**
     * Observes safe output from the real operation
     *
     * @return array<string, mixed>
     */
    private function runConsole(ManagedPolicyConsole $console, string $operation, int $expectedExit = 0): array
    {
        $output = '';
        $error = '';
        $exit = $console->run([$operation, '--internal'], static function (string $text) use (&$output): void {
            $output .= $text;
        }, static function (string $text) use (&$error): void {
            $error .= $text;
        });
        self::assertSame($expectedExit, $exit, $error);
        if ($exit !== 0) {
            self::assertSame('', $output);
            self::assertSame(ManagedPolicyConsole::FAILURE, $error);

            return [];
        }
        self::assertSame('', $error);

        return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Compares complete installed managed definitions and memberships
     */
    private function assertExactPolicy(): void
    {
        $policy = InstallationManagedPolicy::create();
        self::assertCount(25, $this->permissions->getManaged());
        self::assertCount(2, $this->roles->getManaged());
        foreach ($policy->getPermissions() as $definition) {
            $actual = $this->permissions->getById($definition->getId());
            self::assertTrue($actual->isManaged());
            self::assertTrue($actual->getName()->equals($definition->getName()));
            self::assertSame($definition->getTier(), $actual->getTier());
        }
        foreach ($policy->getRoles() as $definition) {
            $actual = $this->roles->getById($definition->getId());
            self::assertTrue($actual->isManaged());
            self::assertTrue($actual->getName()->equals($definition->getName()));
            self::assertEqualsCanonicalizing($definition->getPermissionIds(), $actual->getPermissionIds());
        }
    }

    /**
     * Observes events without assuming callbacks cannot change them
     *
     * @return list<Event>
     */
    private function observedEvents(): array
    {
        return $this->events;
    }

    /**
     * Captures authority including timestamps for rollback and idempotency assertions
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        return [
            'permissions' => $this->connection->fetchAllAssociative('SELECT * FROM permissions ORDER BY id'),
            'roles'       => $this->connection->fetchAllAssociative('SELECT * FROM roles ORDER BY id'),
            'memberships' => $this->connection->fetchAllAssociative(
                'SELECT * FROM role_permissions ORDER BY role_id, permission_id'
            )
        ];
    }

    /**
     * Creates a removable managed fixture outside the installation catalog
     */
    private function obsoletePermission(): Permission
    {
        return Permission::defineManaged(
            PermissionId::generate(),
            PermissionName::fromString('OBSOLETE'),
            PermissionTier::ADMIN_SAFE,
            $this->now()
        );
    }

    /**
     * Supplies a deterministic policy clock
     */
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-11T12:00:00Z');
    }

    /**
     * Opens only the guarded dedicated test database
     */
    private function connection(): Connection
    {
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $connection = DriverManager::getConnection((new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($url));
        $guard->assertConnected($connection, $expected);

        return $connection;
    }
}
