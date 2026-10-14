<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\Locking\AuthenticationAuthorityFences;
use App\Adapter\Persistence\Repository\PostgresAuditEvidenceRepository;
use App\Adapter\Persistence\Repository\PostgresRefreshSessionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Domain\Value\Identifier\Uuid;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Class PersistenceBoundaryTest
 *
 * Proves authority writes cannot silently run without their caller transaction
 */
final class PersistenceBoundaryTest extends TestCase
{
    /**
     * Rejects an authentication fence whose lifetime would end before the write
     */
    public function testAuthenticationFenceRequiresTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::never())->method('executeQuery');
        $this->expectException(LogicException::class);
        (new AuthenticationAuthorityFences($connection))->hold($this->userId());
    }

    /**
     * Rejects detached audit writes before persisting evidence
     */
    public function testAuditEvidenceRequiresTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::never())->method('insert');
        $this->expectException(LogicException::class);
        (new PostgresAuditEvidenceRepository($connection))->add(
            AuditEvidence::record($this->userId()->toString(), 'user.invited', $this->userId())
        );
    }

    /**
     * Rejects detached refresh writes and unchanged revisions before mutation
     */
    public function testRefreshReplacementRequiresTransactionAndAdvancingRevision(): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $session = RefreshSession::start(
            RefreshSessionId::fromString(Uuid::named(Uuid::NAMESPACE_URL, 'https://agent-os.test/session')->toString()),
            $this->userId(),
            RefreshCredential::fromString(str_repeat('a', 64)),
            $at,
            $at->modify('+1 hour'),
            $at->modify('+1 day'),
            0,
            false
        );
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturnOnConsecutiveCalls(true, false);
        $connection->expects(self::never())->method('createQueryBuilder');
        $repository = new PostgresRefreshSessionRepository($connection);
        self::assertFalse($repository->replace($session, $session));
        $this->expectException(LogicException::class);
        $repository->replace($session, $session->revoke());
    }

    /**
     * Supplies a deterministic identity through the owning UUID factory
     */
    private function userId(): UserId
    {
        return UserId::fromString(Uuid::named(Uuid::NAMESPACE_URL, 'https://agent-os.test/owner')->toString());
    }
}
