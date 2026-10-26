<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Responder\Api\V1\Sessions\OwnedSessionsData;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
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
     * Preserves a usable near-expiry session when public seconds collapse activity and deadlines
     */
    public function testUsableNearExpiryProjection(): void
    {
        // R57-01: Ordinary package lifetimes remain precise; the public View truncates to seconds.
        $created = new DateTimeImmutable('2026-10-23T12:00:00.900000+00:00');
        $absolute = $created->modify('+2 days');
        $credential = static fn() => RefreshCredential::fromString(bin2hex(random_bytes(32)));
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            UserId::generate(),
            $credential(),
            $created,
            $created->modify('+1 day'),
            $absolute,
            1,
            false
        );
        foreach (
            [
                '2026-10-24T11:00:00+00:00',
                '2026-10-25T10:00:00+00:00',
                '2026-10-25T12:00:00.100000+00:00'
            ] as $time
        ) {
            $at = new DateTimeImmutable($time);
            $proposed = $at->modify('+1 day');
            $session = $session->rotate($credential(), $at, min($proposed, $absolute));
        }
        self::assertTrue($session->isUsableAt(new DateTimeImmutable('2026-10-25T12:00:00.200000+00:00')));
        $records = ArrayList::of(SessionView::class);
        $records->add(SessionView::fromSession($session, RefreshSessionId::generate()));
        $data = new OwnedSessionsData(new ResultSet(1, 25, 1, $records), [])->toArray();

        // This same approved projection drives the durable client service/page R57-01 regressions.
        self::assertSame([
            'session_id'          => $session->getId()->toString(),
            'browser_family'      => 'Unknown',
            'device_class'        => 'unknown',
            'created_at'          => '2026-10-23T12:00:00+00:00',
            'last_activity_at'    => '2026-10-25T12:00:00+00:00',
            'idle_expires_at'     => '2026-10-25T12:00:00+00:00',
            'absolute_expires_at' => '2026-10-25T12:00:00+00:00',
            'remembered'          => false,
            'current'             => false
        ], $data['records'][0]);
    }

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
