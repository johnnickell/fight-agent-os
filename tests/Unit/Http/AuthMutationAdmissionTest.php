<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\V1\Auth\AuthOperation;
use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Middleware\Api\Auth\AuthEndpointGuard;
use App\Adapter\Http\Middleware\Api\Auth\BrowserMutationGuard;
use App\Adapter\Http\Middleware\Api\Auth\PasswordChangeGuard;
use App\Adapter\Http\Middleware\Api\Auth\ResetCompletionGuard;
use App\Adapter\Http\Middleware\Api\Auth\ResetRequestGuard;
use App\Adapter\Persistence\Authentication\PostgresAuthAttempts;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Adapter\Security\HmacCsrfProofs;
use App\Application\Security\Csrf\Clock\CsrfClock;
use Doctrine\DBAL\Connection;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AccessClock;

/**
 * Proves invalid browser mutations cannot spend an admission budget or reach endpoint work
 */
final class AuthMutationAdmissionTest extends TestCase
{
    private const string ORIGIN = 'https://agent-os.test';
    private const int NOW = 1789999200;

    /**
     * Lists checked JSON bodies with their admission purposes
     *
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function valid_mutations(): iterable
    {
        yield 'login' => ['login', ['email' => 'owner@example.test', 'password' => 'test-only', 'remember' => true]];
        yield 'refresh' => ['refresh', []];
        yield 'reset request' => ['reset', ['email' => 'owner@example.test']];
        yield 'reset completion' => ['complete', self::completion('opaque')];
        yield 'password change' => ['change', []];
    }

    /**
     * Spends an independent admission budget before invoking the next handler
     *
     * @param string $operation
     * @param array<string, mixed> $body
     */
    #[DataProvider('valid_mutations')]
    public function test_that_valid_mutation_spends_budget_before_handler(string $operation, array $body): void
    {
        $budgetSpent = false;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional')->willReturnCallback(
            static function () use (&$budgetSpent): void {
                $budgetSpent = true;
            }
        );
        [$guard, $request] = $this->fixture($connection, $operation, $body);
        $response = (new ResponseFactory())->createResponse(204);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturnCallback(
            static function () use (&$budgetSpent, $response) {
                self::assertTrue($budgetSpent);

                return $response;
            }
        );

        self::assertSame($response, $guard->process($request, $next));
    }

    /**
     * Lists invalid JSON or transport combinations
     *
     * @return iterable<string, array{string, mixed, 2?: array<string, string>}>
     */
    public static function invalid_mutations(): iterable
    {
        yield 'missing parsed login body' => ['login', null];
        yield 'wrong login shape' => ['login', ['email' => 'owner@example.test']];
        yield 'login must use canonical email' => [
            'login', ['email' => 'owner@EXAMPLE.TEST', 'password' => 'test-only', 'remember' => false]
        ];
        yield 'refresh has an empty body only' => ['refresh', ['unexpected' => true]];
        yield 'reset request has no extra fields' => ['reset', ['email' => 'owner@example.test', 'extra' => 1]];
        yield 'reset request rejects malformed address' => ['reset', ['email' => 'not-an-address']];
        yield 'reset request requires canonical address' => ['reset', ['email' => 'owner@EXAMPLE.TEST']];
        yield 'missing parsed completion body' => ['complete', null];
        yield 'incomplete reset body' => ['complete', ['credential' => 'opaque']];
        yield 'completion bounds credential bytes' => ['complete', self::completion(str_repeat('a', 4097))];
        yield 'password change rejects delegated provenance' => ['change', [], ['Forwarded' => 'proto=https']];
        yield 'password change rejects navigation' => ['change', [], ['Sec-Fetch-Mode' => 'navigate']];
        yield 'password change requires JSON' => ['change', [], ['Content-Type' => 'text/plain']];
    }

    /**
     * Rejects malformed mutations before spending budgets or invoking business work
     *
     * @param string $operation
     * @param mixed $body
     * @param array<string, string> $headers
     */
    #[DataProvider('invalid_mutations')]
    public function test_that_invalid_mutation_cannot_spend_budget(
        string $operation,
        mixed $body,
        array $headers = []
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('transactional');
        [$guard, $request] = $this->fixture($connection, $operation, $body);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->expectException(PermissionDenied::class);

        $guard->process($request, $next);
    }

    /**
     * Does not trust headers as a substitute for the server peer identity
     */
    public function test_that_missing_peer_denies_independent_admission(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('transactional');
        [$guard, $request] = $this->fixture($connection, 'change', [], []);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->expectException(RateLimitExceeded::class);

        $guard->process($request, $next);
    }

    /**
     * Maps a spent identity budget to a throttle denial, not a storage exception
     */
    #[DataProvider('identityBudgetOutcomes')]
    public function testIdentityBudgetReturnsDecision(bool $allowed): void
    {
        $connection = $this->createMock(Connection::class);
        $decision = $connection->expects(self::once())->method('transactional');
        if (!$allowed) {
            $decision->willThrowException(new RateLimitExceeded(retryDelaySeconds: 60));
        }
        $admission = new AuthAdmission(new PostgresAuthAttempts($connection, new AccessClock()), self::admissionKey());
        self::assertSame($allowed, $admission->allows(EmailAddress::fromString('owner@example.test')));
    }

    /**
     * Supplies both results of spending an identity budget
     *
     * @return iterable<array{bool}>
     */
    public static function identityBudgetOutcomes(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * Denies reset when the server peer is unknown, before spending any rate budget
     */
    public function testMissingResetPeerDoesNotSpendBudget(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('transactional');
        [$guard, $request] = $this->fixture($connection, 'reset', ['email' => 'owner@example.test'], []);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');
        $this->expectException(RateLimitExceeded::class);

        $guard->process($request, $next);
    }

    /**
     * Refuses a configuration that can silently disable effective network admission
     */
    public function testInvalidNetworkBudget(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid authentication admission configuration.');
        new AuthAdmission(
            new PostgresAuthAttempts($this->createStub(Connection::class), new AccessClock()),
            self::admissionKey(),
            networkLimit: 21
        );
    }

    /**
     * Keeps budget key material out of diagnostics
     */
    public function testBudgetDiagnostics(): void
    {
        $attempts = new PostgresAuthAttempts($this->createStub(Connection::class), new AccessClock());
        self::assertSame(['configuration' => '[redacted]'], new AuthAdmission($attempts, self::admissionKey())
            ->__debugInfo());
    }

    /**
     * Supplies a deterministic test-only HMAC key
     */
    private static function admissionKey(): string
    {
        return bin2hex(implode('', array_map(chr(...), range(0, 31))));
    }

    /**
     * Supplies source-checked completion fields without invoking password handling
     *
     * @return array<string, string>
     */
    private static function completion(string $credential): array
    {
        return [
            'user_id'               => 'checked upstream',
            'password_confirmation' => 'test-only',
            'password'              => 'test-only',
            'credential'            => $credential
        ];
    }

    /**
     * Builds the endpoint guard using an isolated persistence boundary and a fixed proof clock
     *
     * @param Connection $connection
     * @param string $operation
     * @param mixed $body
     * @param array<string, string> $server
     *
     * @return array{MiddlewareInterface, ServerRequestInterface}
     */
    private function fixture(
        Connection $connection,
        string $operation,
        mixed $body,
        array $server = ['REMOTE_ADDR' => '192.0.2.9']
    ): array {
        $admission = new AuthAdmission(
            new PostgresAuthAttempts($connection, new AccessClock()),
            bin2hex(random_bytes(32))
        );
        $proofs = new HmacCsrfProofs(bin2hex(random_bytes(32)), self::ORIGIN);
        $clock = $this->createStub(CsrfClock::class);
        $clock->method('now')->willReturn(self::NOW);
        $browser = new BrowserMutationGuard($proofs, $clock);
        $guard = match ($operation) {
            'login'    => new AuthEndpointGuard($browser, $admission, AuthOperation::LOGIN),
            'refresh'  => new AuthEndpointGuard($browser, $admission, AuthOperation::REFRESH),
            'reset'    => new ResetRequestGuard($browser, $admission),
            'complete' => new ResetCompletionGuard($browser, $admission),
            'change'   => new PasswordChangeGuard($proofs, $admission),
            default    => throw new \LogicException('Unknown fixture operation.'),
        };
        $nonce = str_repeat('a', 64);
        $request = (new ServerRequestFactory())->createServerRequest('POST', self::ORIGIN.'/api/v1/auth', $server)
            ->withHeader('Origin', self::ORIGIN)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cookie', CsrfCookie::NAME.'='.$nonce)
            ->withHeader('X-CSRF-Proof', $proofs->sign($nonce, self::NOW + 30))
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->withAttribute(JsonBody::class, $body);

        return [$guard, $request];
    }
}
