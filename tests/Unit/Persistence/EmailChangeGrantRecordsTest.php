<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\AccessControl\EmailChangeGrant\EmailChangeGrantRecords;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeCredential;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrant;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeRequestOrigin;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\TestCase;

/**
 * Protects original requester and mailbox from substitution during a grant transition
 */
final class EmailChangeGrantRecordsTest extends TestCase
{
    /**
     * Preserves the original requester and mailbox separately from the target and delivery destination
     */
    public function testTransitionRetainsOriginalRequestEvidence(): void
    {
        $target = UserId::generate();
        $requester = UserId::generate();
        $now = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $credential = EmailChangeCredential::fromString(str_repeat('a', 64));
        $grant = EmailChangeGrant::issue(
            $target,
            $credential,
            $now,
            $now->modify('+1 hour'),
            EmailAddress::fromString('new@example.test'),
            'fixture-ciphertext',
            7,
            new EmailChangeRequestOrigin($requester, EmailAddress::fromString('original@example.test'))
        );

        $row = EmailChangeGrantRecords::fields($grant);
        self::assertSame($requester->toString(), $row['requester_id']);
        self::assertSame('original@example.test', $row['old_mailbox']);
        self::assertNotContains(str_repeat('a', 64), $row);

        $stored = EmailChangeGrantRecords::hydrate($row);
        $origin = $stored->getRequestOrigin();
        self::assertTrue($requester->equals($origin->requesterId));
        self::assertSame('original@example.test', $origin->oldMailbox);
        self::assertTrue($target->equals($stored->getUserId()));
        self::assertSame('new@example.test', $stored->getDelivery()->getEmail()->canonical());
        self::assertSame(7, $stored->getEmailChangeReservationRevision());
        self::assertTrue($stored->matchesCredential($credential));

        $consumed = $stored->consume($now->modify('+1 minute'));
        $next = EmailChangeGrantRecords::fields($consumed);
        self::assertSame($requester->toString(), $next['requester_id']);
        self::assertSame('original@example.test', $next['old_mailbox']);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid persisted email reservation binding.');
        EmailChangeGrantRecords::hydrate(array_replace($row, ['email_change_reservation_revision' => 0]));
    }
}
