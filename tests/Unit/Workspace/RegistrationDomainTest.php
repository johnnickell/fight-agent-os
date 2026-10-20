<?php

declare(strict_types=1);

namespace Tests\Unit\Workspace;

use App\Application\Workspace\RegistrationView;
use App\Domain\Workspace\RegistrationInputRejected;
use App\Domain\Workspace\RegistrationIntent;
use App\Domain\Workspace\RegistrationMetadata;
use App\Domain\Workspace\RegistrationSources;
use App\Domain\Workspace\RepositoryId;
use App\Domain\Workspace\RepositoryRegistered;
use App\Domain\Workspace\RepositoryRegistration;
use App\Domain\Workspace\SourceDescriptor;
use App\Domain\Workspace\WorkspaceId;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects stable identity, closed normalization and explicit equivalence without external authority
 */
final class RegistrationDomainTest extends TestCase
{
    /**
     * Distinguishes typed identities from mutable metadata and physical locations
     */
    public function testRegistrationBaselineAndCommittedEventShape(): void
    {
        $workspace = WorkspaceId::generate();
        $actor = UserId::generate();
        $now = new DateTimeImmutable('2026-10-20T12:00:00.123456+00:00');
        $repository = RepositoryRegistration::register(
            $workspace,
            new RegistrationMetadata('Project Alpha', 'project-alpha'),
            new RegistrationSources(),
            $actor,
            $now
        );
        self::assertFalse($workspace->equals(RepositoryId::fromString($workspace->toString())));
        self::assertTrue($repository->id->equals(RepositoryId::fromString($repository->id->toString())));
        self::assertNotSame($repository->id->toString(), $workspace->toString());
        $view = RegistrationView::fromRegistration($repository)->data;
        self::assertSame($workspace->toString(), $view['workspace_id']);
        self::assertSame($repository->id->toString(), $view['repository_id']);
        self::assertSame('active', $view['lifecycle']);
        self::assertSame([1, 1, 1], [
            $view['metadata_revision'], $view['source_revision'], $view['lifecycle_revision']
        ]);
        self::assertSame([$actor->toString(), $actor->toString()], [$view['created_by'], $view['updated_by']]);
        self::assertSame($now->format('Y-m-d\TH:i:s.uP'), $view['created_at']);
        self::assertNull($view['primary_source']);
        self::assertSame([], $view['equivalent_sources']);
        $restored = RepositoryRegistration::reconstitute(
            $repository->id,
            $workspace,
            $repository->metadata,
            $repository->sources,
            $actor,
            $now
        );
        self::assertEquals($repository, $restored);
        $event = new RepositoryRegistered($workspace, $repository->id);
        self::assertEquals($event, RepositoryRegistered::fromArray($event->toArray()));
    }

    /**
     * Normalizes exactly approved forms and retains generic distinctions
     *
     * @phpstan-param array<string, int|string> $expected
     */
    #[DataProvider('canonicalLocators')]
    public function testCanonicalSource(string $type, string $locator, array $expected): void
    {
        $source = SourceDescriptor::fromInput(['type' => $type, 'locator' => $locator]);
        self::assertSame($expected, $source->toArray());
        self::assertSame(json_encode($expected, JSON_THROW_ON_ERROR), $source->key());
        self::assertSame($source->key(), SourceDescriptor::reconstitute($expected)->key());
    }

    /**
     * Supplies independently specified canonical tuples
     *
     * @return iterable<string, array{string, string, array<string, int|string>}>
     */
    public static function canonicalLocators(): iterable
    {
        $github = [
            'type'       => 'github_repository_v1',
            'authority'  => 'github.com',
            'owner'      => 'team-one',
            'repository' => 'project'
        ];
        $aliases = [
            'https://GitHub.COM/Team-One/Project.git',
            'ssh://git@github.com:22/TEAM-One/PROJECT',
            'git@github.com:team-one/project'
        ];
        foreach ($aliases as $index => $locator) {
            yield 'github-'.$index => ['git_network_v1', $locator, $github];
        }
        yield 'one suffix only' => [
            'github_repository_v1', 'https://github.com/team/project.git.git',
            [
                'type'       => 'github_repository_v1',
                'authority'  => 'github.com',
                'owner'      => 'team',
                'repository' => 'project.git'
            ]
        ];
        yield 'explicit github' => ['github_repository_v1', 'https://github.com:443/Team-One/Project', $github];
        yield 'https exact path' => [
            'git_network_v1', 'https://Example.TEST:443/Team/Project.git',
            [
                'type'      => 'git_network_v1',
                'transport' => 'https',
                'host'      => 'example.test',
                'port'      => 443,
                'username'  => '',
                'path_mode' => 'absolute',
                'path'      => 'Team/Project.git'
            ]
        ];
        yield 'ssh absolute' => [
            'git_network_v1', 'ssh://User@example.test:2222/Team/Project',
            [
                'type'      => 'git_network_v1',
                'transport' => 'ssh',
                'host'      => 'example.test',
                'port'      => 2222,
                'username'  => 'User',
                'path_mode' => 'absolute',
                'path'      => 'Team/Project'
            ]
        ];
        yield 'scp home' => [
            'git_network_v1', 'User@Example.TEST:Team/Project',
            [
                'type'      => 'git_network_v1',
                'transport' => 'ssh',
                'host'      => 'example.test',
                'port'      => 22,
                'username'  => 'User',
                'path_mode' => 'home_relative',
                'path'      => 'Team/Project'
            ]
        ];
        yield 'github nonstandard generic' => [
            'git_network_v1', 'ssh://User@github.com:2222/Team/Project',
            [
                'type'      => 'git_network_v1',
                'transport' => 'ssh',
                'host'      => 'github.com',
                'port'      => 2222,
                'username'  => 'User',
                'path_mode' => 'absolute',
                'path'      => 'Team/Project'
            ]
        ];
    }

    /**
     * Refuses unsupported, malformed and credential-bearing descriptors without echoing them
     *
     * @param array<string, mixed> $input
     */
    #[DataProvider('unsafeDescriptors')]
    public function testSourceRefusal(array $input): void
    {
        try {
            SourceDescriptor::fromInput($input);
            self::fail('Unsafe descriptor accepted');
        } catch (RegistrationInputRejected $exception) {
            self::assertSame('Invalid repository registration input.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    /**
     * Supplies closed-union and locator refusals
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unsafeDescriptors(): iterable
    {
        foreach (
            [
            [], ['type' => 'git_local_v1', 'locator' => '/tmp/project'],
            ['type' => 'git_network_v2', 'locator' => 'https://example.test/repo'],
            ['type' => 'git_network_v1', 'locator' => 3],
            ['type' => 'github_repository_v1', 'locator' => 'https://github.com/a/b', 'provider_id' => '123']
            ] as $index => $input
        ) {
            yield 'shape-'.$index => [$input];
        }
        foreach (
            [
            '', 'https://user:secret-marker@example.test/a', 'https://user@example.test/a', 'ssh://example.test/a',
            'ssh://user:secret-marker@example.test/a', 'file:///tmp/repo', 'git://example.test/a',
            'https://example.test./a', 'https://a..test/a', 'https://-a.test/a', 'https://example.test:0/a',
            'https://example.test:65536/a', 'https://example.test/a//b', 'https://example.test/a/../b',
            'https://example.test/a/./b', 'https://example.test/a/', 'https://example.test/a%2fb',
            'https://example.test/a?query', 'https://example.test/a#fragment', 'https://example.test/a\\b',
            'https://éxample.test/a', "https://example.test/a\n", 'https://github.com/a/b/c',
            'https://github.com/a--b/repo', 'https://github.com/a/.git', 'https://github.com/a/..',
            'https://example.test/'.str_repeat('a', 1025), 'https://'.str_repeat('a', 254).'/b',
            str_repeat('a', 2049)
            ] as $index => $locator
        ) {
            yield 'locator-'.$index => [['type' => 'git_network_v1', 'locator' => $locator]];
        }
        yield 'unverified github profile' => [[
            'type' => 'github_repository_v1', 'locator' => 'https://enterprise.example.test/a/b'
        ]];
    }

    /**
     * Rejects noncanonical names rather than silently coercing claims
     */
    #[DataProvider('invalidMetadata')]
    public function testMetadataRefusal(string $name, string $slug): void
    {
        $this->expectException(RegistrationInputRejected::class);
        new RegistrationMetadata($name, $slug);
    }

    /**
     * Supplies reserved, unbounded and ambiguous metadata
     *
     * @return iterable<array{string, string}>
     */
    public static function invalidMetadata(): iterable
    {
        foreach (['', 'Bad', '-bad', 'bad-', 'bad--slug', 'a_b', 'api', str_repeat('a', 81)] as $slug) {
            yield ['Project', $slug];
        }
        foreach (['', ' Project', 'Project ', "Project\nName", "\xff", str_repeat('é', 121)] as $name) {
            yield [$name, 'project'];
        }
    }

    /**
     * Makes equivalence explicit, canonical and payload-bound while keeping forks distinct
     */
    public function testEquivalenceAndIdempotentIntent(): void
    {
        $primary = SourceDescriptor::fromInput([
            'type' => 'git_network_v1', 'locator' => 'https://example.test/Team/Project.git'
        ]);
        $mirror = SourceDescriptor::fromInput([
            'type' => 'git_network_v1', 'locator' => 'git@mirror.test:Team/Project'
        ]);
        $other = SourceDescriptor::fromInput([
            'type' => 'git_network_v1', 'locator' => 'ssh://git@elsewhere.test/Team/Project'
        ]);
        $one = new RegistrationIntent(
            new RegistrationMetadata('Project', 'project'),
            new RegistrationSources($primary, [$mirror, $other]),
            'intent-0001'
        );
        $two = new RegistrationIntent(
            $one->metadata,
            new RegistrationSources($primary, [$other, $mirror]),
            'different-key'
        );
        self::assertSame($one->digest(), $two->digest());
        self::assertCount(3, $one->sources->all());
        $changed = new RegistrationIntent(new RegistrationMetadata('Changed', 'project'), $one->sources, 'intent-0001');
        self::assertNotSame($one->digest(), $changed->digest());
        $fork = SourceDescriptor::fromInput([
            'type' => 'git_network_v1', 'locator' => 'https://github.com/a-fork/project'
        ]);
        $upstream = SourceDescriptor::fromInput([
            'type' => 'git_network_v1', 'locator' => 'https://github.com/upstream/project'
        ]);
        self::assertNotSame($fork->key(), $upstream->key());
        self::assertSame(80, strlen((new RegistrationMetadata('Équipe', str_repeat('a', 80)))->slug));
    }

    /**
     * Rejects duplicate equivalences after canonical normalization
     */
    public function testDuplicateEquivalentSource(): void
    {
        $first = SourceDescriptor::fromInput([
            'type' => 'git_network_v1', 'locator' => 'https://github.com/team/project.git'
        ]);
        $same = SourceDescriptor::fromInput([
            'type' => 'github_repository_v1', 'locator' => 'git@github.com:TEAM/PROJECT'
        ]);
        $this->expectException(RegistrationInputRejected::class);
        new RegistrationSources($first, [$same]);
    }

    /**
     * Rejects unanchored equivalence claims
     */
    public function testEquivalentSourcesRequirePrimary(): void
    {
        $source = SourceDescriptor::fromInput(['type' => 'git_network_v1', 'locator' => 'https://example.test/a']);
        $this->expectException(RegistrationInputRejected::class);
        new RegistrationSources(null, [$source]);
    }

    /**
     * Refuses changed canonical persistence instead of silently repairing it
     */
    public function testNoncanonicalReconstitution(): void
    {
        $this->expectException(RegistrationInputRejected::class);
        SourceDescriptor::reconstitute([
            'type' => 'github_repository_v1', 'authority' => 'github.com', 'owner' => 'TEAM', 'repository' => 'project'
        ]);
    }

    /**
     * Refuses ambiguous idempotency keys
     */
    public function testInvalidIdempotencyKey(): void
    {
        $this->expectException(RegistrationInputRejected::class);
        new RegistrationIntent(new RegistrationMetadata('Project', 'project'), new RegistrationSources(), 'short');
    }
}
