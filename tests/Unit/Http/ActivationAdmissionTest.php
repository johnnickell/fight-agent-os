<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Middleware\Api\Auth\ActivationAdmission;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AccessClock;

/**
 * Proves activation admission does not spend either quota after an admission refusal
 */
final class ActivationAdmissionTest extends TestCase
{
    private const string KEY = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    /**
     * Rejects malformed or low-diversity admission keys
     */
    #[DataProvider('invalid_keys')]
    public function test_that_invalid_key_is_rejected(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid activation admission key.');

        new ActivationAdmission($this->createStub(Connection::class), new AccessClock(), $key);
    }

    /**
     * Lists invalid admission key material
     *
     * @return iterable<string, array{string}>
     */
    public static function invalid_keys(): iterable
    {
        yield 'invalid encoding' => ['not a key'];
        yield 'single repeated byte' => [str_repeat('a', 64)];
    }

    /**
     * Refuses a request without a direct peer without accessing storage or forwarding it
     */
    public function test_that_missing_peer_cannot_start_admission(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('transactional');
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->expectException(RateLimitExceeded::class);

        $this->admission($connection)->process($this->request([]), $next);
    }

    /**
     * Requires the admission write to commit independently of business work
     */
    public function test_that_an_existing_transaction_is_not_reused(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::never())->method('transactional');
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Activation admission requires an independent transaction.');

        $this->admission($connection)->process($this->request(), $next);
    }

    /**
     * Refuses the request when a required budget or the storage capacity is exhausted
     */
    #[DataProvider('exhausted_budgets')]
    public function test_that_exhaustion_does_not_persist_partial_attempts(int|false $peer, int $rows): void
    {
        $connection = $this->connection($peer, $rows);
        $connection->expects(self::once())->method('executeStatement')->with(
            self::stringStartsWith('DELETE FROM activation_rate_limits')
        );
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->expectException(RateLimitExceeded::class);

        $this->admission($connection)->process($this->request(), $next);
    }

    /**
     * Lists exhausted admission budgets
     *
     * @return iterable<string, array{int|false, int}>
     */
    public static function exhausted_budgets(): iterable
    {
        yield 'ten peer attempts' => [10, 2];
        yield 'no room to store a new peer' => [false, 10000];
    }

    /**
     * Records the two distinct quota dimensions for the current fifteen-minute window before dispatch
     */
    public function test_that_success_records_both_dimensions_before_forwarding(): void
    {
        $connection = $this->connection(false, 1);
        $writes = [];
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $statement, array $parameters) use (&$writes): int {
                if (str_starts_with($statement, 'INSERT INTO')) {
                    $writes[] = $parameters;
                }

                return 1;
            }
        );
        $request = $this->request();
        $response = (new ResponseFactory())->createResponse(204);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturnCallback(
            static function () use (&$writes, $response): ResponseInterface {
                $windowEnd = (new DateTimeImmutable('2026-09-28T12:15:00Z'))->getTimestamp();
                self::assertCount(2, $writes);
                self::assertNotSame($writes[0][0], $writes[1][0]);
                foreach ($writes as [$key, $deadline]) {
                    self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $key);
                    self::assertSame($windowEnd, $deadline);
                }

                return $response;
            }
        );

        self::assertSame($response, $this->admission($connection)->process($request, $next));
    }

    /**
     * Isolates the connection while exercising the real quota decision inside its transaction
     */
    private function connection(int|false $peer, int $rows): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn(callable $operation) => $operation());
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(1, $peer, $rows);

        return $connection;
    }

    /**
     * Supplies an instant at a window boundary
     */
    private function admission(Connection $connection): ActivationAdmission
    {
        return new ActivationAdmission(
            $connection,
            new AccessClock(new DateTimeImmutable('2026-09-28T12:00:00Z')),
            self::KEY
        );
    }

    /**
     * Creates an activation request with only trusted server data for its peer
     *
     * @param array<string, string> $server
     */
    private function request(array $server = ['REMOTE_ADDR' => '192.0.2.9']): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest(
            'POST',
            'https://agent-os.test/api/v1/activation',
            $server
        );
    }
}
