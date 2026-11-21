<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Adapter\Http\Action\Api\V1\Invitations\ReadInvitationStatusAction;
use App\Adapter\Http\Api\Invitation\InvitationOperation;
use App\Adapter\Http\Api\Invitation\InvitationRateLimiter;
use App\Adapter\Http\Responder\Api\V1\Invitations\ReadInvitationStatusResponder;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationOrigin;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\CredentialDeliveryAbsent;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;
use TypeError;

/**
 * Protects the nullable package status representation after central HTTP admission
 */
final class InvitationStatusActionTest extends TestCase
{
    /**
     * Distinguishes optional delivery, required target and unsafe dependency results
     */
    #[DataProvider('results')]
    public function testStatusResult(string $kind): void
    {
        $target = User::invite(
            UserId::generate(),
            EmailAddress::fromString('target@example.test'),
            new DateTimeImmutable('2026-11-03T12:00:00Z')
        );
        $actor = UserId::generate();
        $view = new InvitationDeliveryStatusView(
            $target->getId(),
            CredentialDeliveryStatus::RETRY_PENDING,
            new DateTimeImmutable('2026-11-10T12:00:00Z'),
            ActivationDeliveryId::generate(),
            $kind === 'registration' ? ActivationOrigin::REGISTRATION : ActivationOrigin::PRIVATE_INVITATION
        );
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($kind === 'missing-target' ? null : $target);
        $queries = $this->createStub(QueryBus::class);
        $queries->method('fetch')->willReturn(match ($kind) {
            'no-delivery' => new CredentialDeliveryAbsent(),
            'wrong-type' => null,
            default => $view
        });
        $limiter = $this->createMock(InvitationRateLimiter::class);
        $limiter->expects(self::once())->method('consume')->with($actor, InvitationOperation::STATUS);
        $action = new ReadInvitationStatusAction($queries, $users, $limiter, new ReadInvitationStatusResponder(
            new JSendResponseFactory(new ResponseFactory(), new StreamFactory())
        ));
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturn($target->getId()->toString());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/invitations')
            ->withAttribute(AuthenticationContext::class, new AuthenticationContext(
                $actor,
                RefreshSessionId::generate(),
                1
            ))
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
        if ($kind === 'missing-target') {
            $this->expectException(ResourceNotFound::class);
        } elseif ($kind === 'wrong-type') {
            $this->expectException(TypeError::class);
        }
        $response = $action->handle($request);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        if ($kind === 'no-delivery') {
            self::assertNull($data);
        } else {
            self::assertSame([
                'user_id'                => $target->getId()->toString(),
                'status'                 => 'retry_pending',
                'expires_at'             => '2026-11-10T12:00:00+00:00',
                'activation_delivery_id' => $view->getActivationDeliveryId()->toString(),
                'origin'                 => $kind === 'registration' ? 'registration' : 'private_invitation'
            ], $data);
        }
    }

    /**
     * Supplies optional status and malformed dependency categories
     *
     * @return iterable<array{string}>
     */
    public static function results(): iterable
    {
        foreach (['delivery', 'registration', 'no-delivery', 'missing-target', 'wrong-type'] as $kind) {
            yield [$kind];
        }
    }
}
