<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Invitations\CorrectInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\IssueInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\ReadInvitationStatusAction;
use App\Adapter\Http\Action\Api\V1\Invitations\ResendInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\RetryInvitationAction;
use App\Adapter\Http\Responder\Api\V1\Invitations\InvitationMutationResponder;
use App\Adapter\Http\Responder\Api\V1\Invitations\IssueInvitationResponder;
use App\Adapter\Http\Responder\Api\V1\Invitations\ReadInvitationStatusResponder;
use App\Adapter\Persistence\Invitation\PostgresInvitationRateLimiter;
use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\PermissionGuard;
use App\Application\Invitation\ConsoleAssignmentPolicy;
use App\Application\Invitation\InvitationRateLimiter;
use App\Application\Invitation\IssueInvitation;
use App\Domain\Invitation\InvitationRatePolicy;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\QueryHandler\FindInvitationDeliveryStatusHandler;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(FindInvitationDeliveryStatusHandler::class, static fn(Container $container) =>
        new FindInvitationDeliveryStatusHandler($container->get(ActivationGrantRepository::class)));
    $container->get('messaging.query.router')->registerHandlers([
        FindInvitationDeliveryStatusHandler::queryRegistration() => FindInvitationDeliveryStatusHandler::class
    ]);
    $container->set(PermissionGuard::class, static fn(): PermissionGuard => new PermissionGuard());
    $container->set(AuthenticatedPermissions::class, static fn(Container $container): AuthenticatedPermissions =>
        new AuthenticatedPermissions(
            $container->get(AuthoritativePrincipalResolver::class),
            $container->get(PermissionGuard::class)
        ));
    $container->set(InvitationRatePolicy::class, static function (): InvitationRatePolicy {
        $integer = static function (string $name, int $default): int {
            $raw = getenv($name);
            if ($raw === false || $raw === '') {
                return $default;
            }
            if (!preg_match('/\A[1-9][0-9]{0,5}\z/D', $raw)) {
                throw new InvalidArgumentException('Invalid invitation rate configuration.');
            }

            return (int) $raw;
        };

        return new InvitationRatePolicy(
            $integer('APP_INVITATION_RATE_WINDOW', 60),
            $integer('APP_INVITATION_RATE_ISSUE_LIMIT', 10),
            $integer('APP_INVITATION_RATE_STATUS_LIMIT', 60),
            $integer('APP_INVITATION_RATE_CAPACITY', 10000),
            $integer('APP_INVITATION_RATE_RETRY_LIMIT', 5),
            $integer('APP_INVITATION_RATE_RESEND_LIMIT', 3),
            $integer('APP_INVITATION_RATE_CORRECT_LIMIT', 3)
        );
    });
    $container->set(InvitationRateLimiter::class, static fn(Container $container): InvitationRateLimiter =>
        new PostgresInvitationRateLimiter(
            $container->get(Connection::class),
            $container->get(Clock::class),
            $container->get(InvitationRatePolicy::class)
        ));
    $container->set(InvitationMutationResponder::class, static fn(Container $container): InvitationMutationResponder =>
        new InvitationMutationResponder($container->get(JSendResponseFactory::class)));
    foreach ([RetryInvitationAction::class, ResendInvitationAction::class, CorrectInvitationAction::class] as $action) {
        $container->set($action, static fn(Container $container) => new $action(
            $container->get(CommandBus::class),
            $container->get(UserRepository::class),
            $container->get(InvitationRateLimiter::class),
            $container->get(InvitationMutationResponder::class)
        ));
    }
    $container->set(IssueInvitation::class, static fn(Container $container): IssueInvitation => new IssueInvitation(
        $container->get(AuthenticatedPermissions::class),
        $container->get(InvitationRateLimiter::class),
        $container->get(ConsoleAssignmentPolicy::class),
        $container->get(UserRepository::class),
        $container->get(CommandBus::class),
        $container->get(QueryBus::class),
        $container->get(TransactionalUnitOfWork::class),
        $container->get(AuditEvidenceRepository::class)
    ));
    $container->set(IssueInvitationResponder::class, static fn(Container $container): IssueInvitationResponder =>
        new IssueInvitationResponder($container->get(JSendResponseFactory::class)));
    $container->set(ReadInvitationStatusResponder::class, static fn(
        Container $container
    ): ReadInvitationStatusResponder =>
        new ReadInvitationStatusResponder($container->get(JSendResponseFactory::class)));
    $container->set(IssueInvitationAction::class, static fn(Container $container): IssueInvitationAction =>
        new IssueInvitationAction(
            $container->get(IssueInvitation::class),
            $container->get(IssueInvitationResponder::class)
        ));
    $container->set(ReadInvitationStatusAction::class, static fn(Container $container): ReadInvitationStatusAction =>
        new ReadInvitationStatusAction(
            $container->get(QueryBus::class),
            $container->get(UserRepository::class),
            $container->get(InvitationRateLimiter::class),
            $container->get(ReadInvitationStatusResponder::class)
        ));
};
