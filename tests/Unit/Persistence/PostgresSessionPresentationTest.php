<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\SessionPresentation\PostgresSessionPresentation;
use App\Application\Security\Session\BrowserFamily;
use App\Application\Security\Session\DeviceClass;
use App\Application\Security\Session\DeviceDescriptor;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Protects bounded persistence of coarse session presentation data
 */
final class PostgresSessionPresentationTest extends TestCase
{
    private Connection&MockObject $connection;
    private PostgresSessionPresentation $presentation;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->presentation = new PostgresSessionPresentation($this->connection);
    }

    /**
     * Writes only coarse labels and expiry without replacing an earlier capture
     */
    public function testCapturePreservesFirstObservation(): void
    {
        $id = RefreshSessionId::generate();
        $expiry = new DateTimeImmutable('2026-10-20T12:00:00.123456Z');
        $this->connection->expects(self::once())->method('executeStatement')->with(
            self::stringContains('ON CONFLICT (session_id) DO NOTHING'),
            [$id->toString(), 'Chrome', 'desktop', '2026-10-20 12:00:00.123456+00:00']
        )->willReturn(0);

        $this->presentation->capture($id, new DeviceDescriptor(BrowserFamily::CHROME, DeviceClass::DESKTOP), $expiry);
    }

    /**
     * Returns descriptors only for stored identities and maps unfamiliar labels to Unknown
     */
    public function testDescriptorsKeepStoredIdentitiesAndUseSafeFallbacks(): void
    {
        $known = RefreshSessionId::generate()->toString();
        $missing = RefreshSessionId::generate()->toString();
        $unknown = RefreshSessionId::generate()->toString();
        $result = $this->createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['session_id' => $known, 'browser_family' => 'Safari', 'device_class' => 'mobile'],
            ['session_id' => $unknown, 'browser_family' => 'unrecognized', 'device_class' => 'unrecognized']
        ]);
        $this->connection->expects(self::once())->method('executeQuery')->with(
            self::stringContains('WHERE session_id IN (?)'),
            [[$known, $missing, $unknown]],
            [ArrayParameterType::STRING]
        )->willReturn($result);

        self::assertEquals([
            $known   => new DeviceDescriptor(BrowserFamily::SAFARI, DeviceClass::MOBILE),
            $unknown => new DeviceDescriptor(BrowserFamily::UNKNOWN, DeviceClass::UNKNOWN)
        ], $this->presentation->descriptors([$known, $missing, $unknown]));
    }

    /**
     * Avoids querying storage for an empty or oversized page
     */
    public function testDescriptorQueryIsBounded(): void
    {
        $this->connection->expects(self::never())->method('executeQuery');

        self::assertSame([], $this->presentation->descriptors([]));
        $this->expectException(InvalidArgumentException::class);
        $this->presentation->descriptors(array_fill(0, 101, RefreshSessionId::generate()->toString()));
    }

    /**
     * Requests an ordered bounded deletion and returns the actual affected count
     */
    public function testPruneUsesTheSessionLifecycleAndReportsDeletions(): void
    {
        $this->connection->expects(self::once())->method('executeStatement')->with(
            self::callback(static function (string $sql): bool {
                self::assertStringContainsString('LEFT JOIN refresh_sessions', $sql);
                self::assertStringContainsString('s.id IS NULL OR s.revoked', $sql);
                self::assertStringContainsString('s.idle_expires_at <= CURRENT_TIMESTAMP', $sql);
                self::assertStringContainsString('s.absolute_expires_at <= CURRENT_TIMESTAMP', $sql);

                return str_contains($sql, 'ORDER BY p.expires_at, p.session_id LIMIT ?');
            }),
            [1000],
            [ParameterType::INTEGER]
        )->willReturn('14');

        self::assertSame(14, $this->presentation->prune(1000));
    }

    /**
     * Rejects out-of-range deletion bounds before writing
     */
    #[DataProvider('invalidLimits')]
    public function testPruneRejectsInvalidLimit(int $limit): void
    {
        $this->connection->expects(self::never())->method('executeStatement');

        $this->expectException(InvalidArgumentException::class);
        $this->presentation->prune($limit);
    }

    /**
     * Supplies limits outside the supported interval
     *
     * @return iterable<string, array{int}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'no work' => [0];
        yield 'unbounded work' => [1001];
    }
}
