<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Responder\Api\V1\Users\UserRoleData;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves complete, unambiguous endpoint role mapping from already fetched safe Views
 */
final class UserRoleDataTest extends TestCase
{
    /**
     * Sorts endpoint roles independently of query order and preserves empty assignments
     */
    public function testPresentation(): void
    {
        $now = new DateTimeImmutable('2026-11-02T12:00:00Z');
        $managed = Role::defineManaged(RoleId::generate(), RoleName::fromString('ROLE_USER'), [], $now);
        $custom = Role::define(RoleId::generate(), RoleName::fromString('ROLE_CUSTOM'), [], $now);
        $user = $this->view([$managed->getId(), $custom->getId()]);
        $empty = $this->view([]);
        self::assertSame([
            $user->getUserId()->toString()  => [
                ['name' => 'ROLE_CUSTOM', 'managed' => false],
                ['name' => 'ROLE_USER', 'managed' => true]
            ],
            $empty->getUserId()->toString() => []
        ], UserRoleData::map([$user, $empty], [RoleView::fromRole($managed), RoleView::fromRole($custom)]));
        self::assertSame([], UserRoleData::map([], []));
    }

    /**
     * Rejects partial or ambiguous endpoint data instead of silently inventing assignments
     */
    #[DataProvider('badReferences')]
    public function testRoleIntegrity(string $fault): void
    {
        $now = new DateTimeImmutable('2026-11-02T12:00:00Z');
        $managed = Role::defineManaged(RoleId::generate(), RoleName::fromString('ROLE_USER'), [], $now);
        $custom = Role::define(RoleId::generate(), RoleName::fromString('ROLE_CUSTOM'), [], $now);
        $roles = match ($fault) {
            'missing' => [$managed],
            'duplicate' => [$managed, $managed, $custom],
            'unexpected' => [$managed, Role::define(
                RoleId::generate(),
                RoleName::fromString('ROLE_OTHER'),
                [],
                $now
            )],
            'name' => [$managed, Role::defineManaged($custom->getId(), $managed->getName(), [], $now)],
            default => [$managed, $custom]
        };
        $ids = [$managed->getId(), $custom->getId()];
        if ($fault === 'assignment') {
            $ids[] = $custom->getId();
        }
        $user = $this->view($ids);
        $this->expectException(LogicException::class);
        UserRoleData::map(
            $fault === 'user' ? [$user, $user] : [$user],
            array_map(RoleView::fromRole(...), $roles)
        );
    }

    /**
     * Supplies each exact-reference presentation failure
     *
     * @return iterable<array{string}>
     */
    public static function badReferences(): iterable
    {
        foreach (['missing', 'duplicate', 'unexpected', 'name', 'assignment', 'user'] as $fault) {
            yield [$fault];
        }
    }

    /**
     * Creates a package View with deliberately explicit reference data
     *
     * @param array $ids
     *
     * @phpstan-param list<RoleId> $ids
     */
    private function view(array $ids): UserView
    {
        $now = new DateTimeImmutable('2026-11-02T12:00:00Z');

        return new UserView(
            UserId::generate(),
            EmailAddress::fromString('target@example.test'),
            UserState::PENDING_ACTIVATION,
            $ids,
            $now,
            $now
        );
    }
}
