<?php

declare(strict_types=1);

namespace App\Application\CredentialDelivery;

use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\DeliverUserInvitation;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\ActivationDeliveryNotRetryableException;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\DueCredentialDelivery;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Exception\CredentialDeliveryTransitionException;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\DeliverPasswordReset;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Exception\PasswordResetDeliveryException;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use InvalidArgumentException;
use Throwable;

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
     * Stops on a raced generation because a package transaction rollback closes the Doctrine manager
     * A later invocation starts a fresh worker; unexpected failures propagate without being classified as contention
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
        foreach ($work as $delivery) {
            $id = $delivery->getDeliveryId()->toString();
            $command = match ($delivery->getPurpose()) {
                'activation' => new DeliverUserInvitation(
                    'credential-recovery',
                    $delivery->getUserId(),
                    ActivationDeliveryId::fromString($id)
                ),
                'password_reset' => new DeliverPasswordReset(
                    'credential-recovery',
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
            } catch (Throwable $failure) {
                if (
                    !$failure instanceof ActivationDeliveryNotRetryableException
                    && !$failure instanceof PasswordResetDeliveryException
                    && !$failure instanceof CredentialDeliveryTransitionException
                ) {
                    throw $failure;
                }
                $report['contended']++;
                $report['stopped'] = true;
                break;
            }
        }

        return $report;
    }
}
