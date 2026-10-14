<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Action\Api\V1\Me\ReadCurrentPrincipalAction;
use App\Adapter\Http\Action\Api\V1\Validations\ReadValidationAction;
use App\Adapter\Http\Api\Auth\ActionAccess;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves metadata interpretation directly without registering fixture product endpoints
 */
final class ActionAccessTest extends TestCase
{
    /**
     * Admits only the actual approved public Actions and the authenticated current-principal query
     */
    public function testActualActions(): void
    {
        self::assertTrue(ActionAccess::inspect(CsrfBootstrapAction::class.':handle')->public);
        self::assertTrue(ActionAccess::inspect(ReadValidationAction::class.':handle')->public);
        $me = ActionAccess::inspect(ReadCurrentPrincipalAction::class.':handle');
        self::assertFalse($me->public);
        self::assertSame([], $me->permissions);
    }

    /**
     * Interprets every declared permission without constructing an Action
     */
    public function testConjunctionWithoutConstruction(): void
    {
        $access = ActionAccess::inspect(PermissionDeclaration::class.':handle');
        self::assertFalse($access->public);
        self::assertSame(['READ_USERS', 'READ_ROLES'], array_map(
            static fn($name): string => $name->toString(),
            $access->permissions
        ));
    }

    /**
     * Rejects missing, ambiguous and malformed metadata without resolving constructors
     */
    #[DataProvider('invalidDeclarations')]
    public function testInvalidDeclarations(mixed $callable): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid API Action access declaration.');
        ActionAccess::inspect($callable);
    }

    /**
     * Supplies isolated declaration values rather than HTTP routes
     *
     * @return iterable<string, array{mixed}>
     */
    public static function invalidDeclarations(): iterable
    {
        yield 'closure' => [static fn() => null];
        yield 'unknown class' => ['MissingAction:handle'];
        yield 'wrong method' => [ReadCurrentPrincipalAction::class.':other'];
        foreach (
            [
            MissingAccess::class, ConflictingAccess::class, UnapprovedPublic::class,
            InvalidPermission::class, InvalidMarker::class, DuplicateMarker::class,
            MethodAccess::class, NonFinalAccess::class, StaticAccess::class
            ] as $class
        ) {
            yield $class => [$class.':handle'];
        }
    }
}
