<?php

declare(strict_types=1);

namespace App\Application\CredentialDelivery;

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
 * Class RecoverCredentialDeliveryPage
 *
 * Offers one bounded discovery page to registered package delivery handlers
 */
final readonly class RecoverCredentialDeliveryPage
{
    /**
     * Constructs RecoverCredentialDeliveryPage
     */
    public function __construct(private QueryBus $queries, private CommandBus $commands, private Clock $clock)
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
        $work = $this->queries->fetch(new FindDueCredentialDeliveries($this->clock->now(), min($pageSize, $capacity)));
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
            $id = $delivery->getDeliveryId()->toString();
            $command = match ($delivery->getPurpose()) {
                'activation' => new DeliverUserInvitation(
                    $systemId,
                    $delivery->getUserId(),
                    ActivationDeliveryId::fromString($id)
                ),
                'password_reset' => new DeliverPasswordReset(
                    $systemId,
                    $delivery->getUserId(),
                    PasswordResetDeliveryId::fromString($id)
                ),
                default => null
            };
            if ($command === null) {
                $report['unsupported']++;
                continue;
            }

            $report['offered']++;
            try {
                $this->commands->execute($command);
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
