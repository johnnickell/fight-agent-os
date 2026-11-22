<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Adapter\Http\Action\Api\V1\Invitations\CorrectInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\ResendInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\RetryInvitationAction;
use App\Adapter\Http\Api\Invitation\InvitationOperation;
use App\Adapter\Http\Api\Invitation\InvitationRateLimiter;
use App\Adapter\Http\Api\Invitation\InvitationRatePolicy;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Responder\Api\V1\Invitations\InvitationMutationResponder;
use App\Application\Failure\InvitationRecoveryUncertain;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\ResendInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\RetryInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\ActivationDeliveryNotResendableException;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\ActivationDeliveryNotRetryableException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\Command\CorrectPendingInvitation;
use Fight\AccessControl\Domain\AccessControl\User\Exception\PendingInvitationCorrectionException;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;
use Throwable;

/**
 * Protects admitted recovery transport contracts without duplicating central HTTP authorization
 */
final class InvitationRecoveryTest extends TestCase
{
    /** @var list<Command> */
    private array $commands = [];

    /**
     * Sends trusted actor and canonical input while acknowledging no user data
     */
    #[DataProvider('operations')]
    public function testDispatch(string $operation): void
    {
        [$action, $request, $actor, $target] = $this->action($operation);
        $response = $action->handle($request);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(['status' => 'success', 'data' => null], json_decode(
            (string) $response->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR
        ));
        self::assertCount(1, $this->commands);
        $command = $this->commands[0];
        self::assertInstanceOf(match ($operation) {
            'retry' => RetryInvitationDelivery::class,
            'resend' => ResendInvitationDelivery::class,
            default => CorrectPendingInvitation::class
        }, $command);
        self::assertSame($actor->toString(), (string) $command->getActorId());
        self::assertTrue($target->equals($command->getUserId()));
        if ($command instanceof CorrectPendingInvitation) {
            self::assertSame('corrected@example.test', $command->getEmail()->toString());
        }
    }

    /**
     * Maps only intentional package rejection and post-commit notification uncertainty
     *
     * @phpstan-param class-string<Throwable> $expected
     */
    #[DataProvider('failures')]
    public function testFailures(string $operation, Throwable $failure, string $expected): void
    {
        [$action, $request] = $this->action($operation, $failure);
        $this->expectException($expected);
        $action->handle($request);
    }

    /**
     * Supplies expected rejection and unknown internal failures for every operation
     *
     * @return iterable<array{string, Throwable, class-string<Throwable>}>
     */
    public static function failures(): iterable
    {
        foreach (['retry', 'resend', 'correct'] as $operation) {
            yield [$operation, new EventDispatchFailed([]), InvitationRecoveryUncertain::class];
            yield [$operation, new LogicException('private internal bug'), LogicException::class];
        }
        yield ['retry', new ActivationDeliveryNotRetryableException(), StateConflict::class];
        yield ['resend', new ActivationDeliveryNotResendableException(), StateConflict::class];
        yield ['correct', new PendingInvitationCorrectionException(), StateConflict::class];
        foreach (['retry', 'resend', 'correct'] as $operation) {
            yield [$operation, new StateConflict('The invitation changed concurrently.'), StateConflict::class];
        }
    }

    /**
     * Supplies distinct recovery operations
     *
     * @return iterable<array{string}>
     */
    public static function operations(): iterable
    {
        yield ['retry'];
        yield ['resend'];
        yield ['correct'];
    }

    /**
     * Rejects invalid recovery requests without consuming a budget or sending a command
     *
     * @phpstan-param class-string<Throwable> $expected
     */
    #[DataProvider('rejectedRequests')]
    public function testRejectsInvalidRequest(string $operation, string $rejection, string $expected): void
    {
        [$action, $request] = $this->action($operation, rejection: $rejection);
        $this->expectException($expected);
        $action->handle($request);
    }

    /**
     * Supplies each recovery operation with an independent admission failure
     *
     * @return iterable<array{string, string, class-string<Throwable>}>
     */
    public static function rejectedRequests(): iterable
    {
        foreach (['retry', 'resend', 'correct'] as $operation) {
            yield "$operation missing authentication" => [$operation, 'unauthenticated', AuthenticationRequired::class];
            yield "$operation malformed target" => [$operation, 'malformed', ValidationException::class];
            yield "$operation unknown target" => [$operation, 'missing', PermissionDenied::class];
        }
    }

    /**
     * Rejects disabling or unbounded recovery budgets
     */
    #[DataProvider('invalidBudgets')]
    public function testInvalidBudget(int $retry, int $resend, int $correct): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InvitationRatePolicy(retryLimit: $retry, resendLimit: $resend, correctLimit: $correct);
    }

    /**
     * Supplies lower and upper bounds for recovery budgets
     *
     * @return iterable<array{int, int, int}>
     */
    public static function invalidBudgets(): iterable
    {
        yield [0, 1, 1];
        yield [1001, 1, 1];
        yield [1, 0, 1];
        yield [1, 1001, 1];
        yield [1, 1, 0];
        yield [1, 1, 1001];
    }

    /**
     * Builds an admitted request with observable package dispatch and throttle admission
     *
     * @return array{RetryInvitationAction|ResendInvitationAction|CorrectInvitationAction, ServerRequestInterface,
     *     UserId, UserId}
     */
    private function action(string $operation, ?Throwable $failure = null, ?string $rejection = null): array
    {
        $actor = UserId::generate();
        $target = User::invite(
            UserId::generate(),
            EmailAddress::fromString('pending@example.test'),
            new DateTimeImmutable()
        );
        $users = $this->createMock(UserRepository::class);
        if ($rejection === null || $rejection === 'missing') {
            $users->expects(self::once())->method('getById')->with($target->getId())
                ->willReturn($rejection === 'missing' ? null : $target);
        } else {
            $users->expects(self::never())->method('getById');
        }
        $bus = $this->createMock(CommandBus::class);
        $limiter = $this->createMock(InvitationRateLimiter::class);
        if ($rejection !== null) {
            $bus->expects(self::never())->method('execute');
            $limiter->expects(self::never())->method('consume');
        } else {
            $bus->expects(self::once())->method('execute')->willReturnCallback(function (Command $command) use (
                $failure
            ): void {
                if ($failure !== null) {
                    throw $failure;
                }
                $this->commands[] = $command;
            });
            $limiter->expects(self::once())->method('consume')->with(
                $actor,
                InvitationOperation::from($operation),
                $target->getId()
            );
        }
        $responder = new InvitationMutationResponder(new JSendResponseFactory(
            new ResponseFactory(),
            new StreamFactory()
        ));
        $class = match ($operation) {
            'retry' => RetryInvitationAction::class,
            'resend' => ResendInvitationAction::class,
            default => CorrectInvitationAction::class
        };
        $action = new $class($bus, $users, $limiter, $responder);
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturn(
            $rejection === 'malformed' ? 'not-a-user-id' : $target->getId()->toString()
        );
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/invitations')
            ->withAttribute(JsonBody::class, ['email' => 'Corrected@EXAMPLE.test'])
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));

        if ($rejection !== 'unauthenticated') {
            $request = $request->withAttribute(AuthenticationContext::class, new AuthenticationContext(
                $actor,
                RefreshSessionId::generate(),
                1
            ));
        }

        return [$action, $request, $actor, $target->getId()];
    }
}
