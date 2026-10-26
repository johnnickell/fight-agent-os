<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Responder\Api\V1\Sessions\OwnedSessionsData;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\ResultSet;
use PHPUnit\Framework\TestCase;

/**
 * Proves canonical UTC projection independently of PostgreSQL connection timezone
 */
final class OwnedSessionsDataTest extends TestCase
{
    /**
     * Maps precise package instants to public UTC seconds without exposing owner identity
     */
    public function testUtcProjection(): void
    {
        $created = new DateTimeImmutable('2026-10-25T12:00:00.123456+05:30');
        $view = new SessionView(
            RefreshSessionId::generate(),
            UserId::generate(),
            $created,
            $created->modify('+1 hour'),
            $created->modify('+1 day'),
            $created->modify('+2 days'),
            false,
            true
        );
        $records = ArrayList::of(SessionView::class);
        $records->add($view);
        $data = new OwnedSessionsData(new ResultSet(1, 25, 1, $records), [])->toArray();
        $record = $data['records'][0];
        self::assertSame('2026-10-25T06:30:00+00:00', $record['created_at']);
        self::assertSame('2026-10-25T07:30:00+00:00', $record['last_activity_at']);
        self::assertSame('2026-10-26T06:30:00+00:00', $record['idle_expires_at']);
        self::assertSame('2026-10-27T06:30:00+00:00', $record['absolute_expires_at']);
        self::assertArrayNotHasKey('user_id', $record);
    }
}
