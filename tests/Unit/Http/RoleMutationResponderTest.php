<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Responder\Api\V1\Authority\RoleMutationResponder;
use App\Adapter\Persistence\PersistenceConflict;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Role\Exception\RoleConflictException;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRevision;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\PreconditionFailed;
use Fight\Common\Application\Error\Exception\RuleViolation;
use Fight\Common\Application\Error\Exception\StateConflict;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Throwable;

/**
 * Protects the public Role mutation response contract without a bus or database
 */
final class RoleMutationResponderTest extends TestCase
{
    /**
     * Returns the created Role without exposing permission membership
     */
    public function testCreatedRole(): void
    {
        $id = RoleId::generate();
        $revision = RoleRevision::initial();
        $view = new RoleView($id, RoleName::fromString('ROLE_EDITOR'), false, [PermissionId::generate()], $revision);

        $response = $this->responder()->respond(static fn(): RoleView => $view, 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(['status' => 'success', 'data' => [
            'role_id'  => $id->toString(),
            'name'     => 'ROLE_EDITOR',
            'managed'  => false,
            'revision' => $revision->toString()
        ]], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * Returns only the supplied creation identity
     */
    public function testCreationReturnsOnlyIdentity(): void
    {
        $id = RoleId::generate();
        $response = $this->responder()->respond(static fn(): RoleId => $id, 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['status' => 'success', 'data' => ['role_id' => $id->toString()]], json_decode(
            (string) $response->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR
        ));
    }

    /**
     * Returns an empty success envelope for removal
     */
    public function testRemovedRole(): void
    {
        $response = $this->responder()->respond(static fn(): ?RoleView => null);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(['status' => 'success', 'data' => null], json_decode(
            (string) $response->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR
        ));
    }

    /**
     * Classifies known refusals as conflicts without copying private diagnostics
     */
    #[DataProvider('knownRefusals')]
    public function testKnownRefusal(Throwable $refusal): void
    {
        try {
            $this->responder()->respond(static fn() => throw $refusal);
            self::fail('Expected a state conflict.');
        } catch (StateConflict $conflict) {
            self::assertSame($refusal, $conflict->getPrevious());
            self::assertSame((new StateConflict())->getMessage(), $conflict->getMessage());
        }
    }

    /**
     * Supplies an exact stale Role revision and other known conflict categories
     *
     * @return iterable<array{Throwable}>
     */
    public static function knownRefusals(): iterable
    {
        yield 'stale Role revision in JSON' => [new PreconditionFailed(previous: new RoleConflictException())];
        yield 'Role conflict' => [new RoleConflictException()];
        yield 'persistence conflict' => [new PersistenceConflict('private-storage-marker')];
        yield 'package rule refusal' => [new RuleViolation('private-rule-marker')];
    }

    /**
     * Leaves unrelated preconditions and unknown failures unchanged
     */
    #[DataProvider('unrelatedFailures')]
    public function testUnrelatedFailure(Throwable $failure): void
    {
        try {
            $this->responder()->respond(static fn() => throw $failure);
            self::fail('Expected the original failure.');
        } catch (\Exception $actual) {
            self::assertSame($failure, $actual);
        }
    }

    /**
     * Supplies failures that must not become a Role conflict
     *
     * @return iterable<array{Throwable}>
     */
    public static function unrelatedFailures(): iterable
    {
        yield 'unrelated precondition' => [new PreconditionFailed(previous: new LogicException('unrelated'))];
        yield 'unknown failure' => [new LogicException('private-storage-marker')];
    }

    /**
     * Builds the responder with in-memory PSR-7 messages
     */
    private function responder(): RoleMutationResponder
    {
        return new RoleMutationResponder(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()));
    }
}
