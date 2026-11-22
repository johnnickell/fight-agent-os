<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\DeliverUserInvitation;
use Fight\AccessControl\Domain\AccessControl\Audit\SystemId;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\DueCredentialDelivery;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\DeliverPasswordReset;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use InvalidArgumentException;

/**
 * Class CredentialRecoveryRunner
 *
 * Offers one bounded discovery page to registered package delivery handlers
 */
final readonly class CredentialRecoveryRunner
{
    /**
     * Constructs CredentialRecoveryRunner
     */
    public function __construct(private QueryBus $queryBus, private CommandBus $commandBus, private Clock $clock)
    {
    }

    /**
     * Runs sequentially within operational capacity without selecting package lifecycle policy
     *
     * Stops on package state conflict; a later invocation discovers current work again
     * Unexpected failures propagate without being classified as contention
     *
     * @return array{discovered: int, offered: int, dispatched: int, contended: int, unsupported: int, stopped: bool}
     */
    public function run(int $pageSize, int $capacity): array
    {
        if ($pageSize < 1 || $pageSize > 1000 || $capacity < 1 || $capacity > 1000) {
            throw new InvalidArgumentException('Page size and capacity must be between 1 and 1000.');
        }

        /**
         * @var list<DueCredentialDelivery> $work
         */
        $work = $this->queryBus->fetch(new FindDueCredentialDeliveries($this->clock->now(), min($pageSize, $capacity)));
        $report = [
            'discovered'  => count($work),
            'offered'     => 0,
            'dispatched'  => 0,
            'contended'   => 0,
            'unsupported' => 0,
            'stopped'     => false
        ];
        $systemId = SystemId::generate();
        foreach ($work as $delivery) {
            $deliveryId = $delivery->getDeliveryId();
            $command = match (true) {
                $deliveryId instanceof ActivationDeliveryId => new DeliverUserInvitation(
                    $systemId,
                    $delivery->getUserId(),
                    $deliveryId
                ),
                $deliveryId instanceof PasswordResetDeliveryId => new DeliverPasswordReset(
                    $systemId,
                    $delivery->getUserId(),
                    $deliveryId
                ),
                default => null
            };
            if ($command === null) {
                $report['unsupported']++;
                continue;
            }

            $report['offered']++;
            try {
                $this->commandBus->execute($command);
                $report['dispatched']++;
            } catch (StateConflict) {
                $report['contended']++;
                $report['stopped'] = true;
                break;
            }
        }

        return $report;
    }
}
