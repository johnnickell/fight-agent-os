<?php

declare(strict_types=1);

namespace App\Application\CredentialDelivery;

use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\ExpireInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\ExpiredCredentialDelivery;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindExpiredCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\Command\ExpireEmailChange;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\ExpirePasswordResetDelivery;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use InvalidArgumentException;
use LogicException;

/**
 * Class ExpireCredentialDeliveryPages
 *
 * Dispatches bounded package cleanup and observes progress without inferring lifecycle success
 */
final readonly class ExpireCredentialDeliveryPages
{
    /**
     * Constructs ExpireCredentialDeliveryPages
     */
    public function __construct(
        private QueryBus $queries,
        private SynchronousCommandBus $commands,
        private Clock $clock
    ) {
    }

    /**
     * Runs cleanup at one boundary and stops on unchanged work or a finite dispatch budget
     *
     * @return array{pages: int, dispatched: int, remaining: int, stalled: bool, budget_exhausted: bool}
     */
    public function run(int $pageSize = 50, int $maxPages = 10): array
    {
        if ($pageSize < 1 || $pageSize > 100 || $maxPages < 1 || $maxPages > 10) {
            throw new InvalidArgumentException('Expiry requires a page size of 1–100 and a page budget of 1–10.');
        }
        $at = $this->clock->now();
        $query = new FindExpiredCredentialDeliveries($at, $pageSize);
        $report = ['pages' => 0, 'dispatched' => 0, 'remaining' => 0, 'stalled' => false, 'budget_exhausted' => false];
        $previous = [];
        while (true) {
            /**
             * @var list<ExpiredCredentialDelivery> $work
             */
            $work = $this->queries->fetch($query);
            $report['remaining'] = count($work);
            if ($work === []) {
                return $report;
            }
            $snapshot = array_map(static fn(ExpiredCredentialDelivery $item): array => $item->toArray(), $work);
            if ($snapshot === $previous) {
                $report['stalled'] = true;

                return $report;
            }
            if ($report['pages'] === $maxPages) {
                $report['budget_exhausted'] = true;

                return $report;
            }
            $previous = $snapshot;
            foreach ($work as $item) {
                $id = $item->getDeliveryId()->toString();
                $command = match ($item->getPurpose()) {
                    'activation' => new ExpireInvitationDelivery(
                        'credential-recovery',
                        $item->getUserId(), ActivationDeliveryId::fromString($id), $at
                    ),
                    'password_reset' => new ExpirePasswordResetDelivery(
                        'credential-recovery',
                        $item->getUserId(), PasswordResetDeliveryId::fromString($id), $at
                    ),
                    'email_change' => new ExpireEmailChange(
                        'credential-recovery',
                        $item->getUserId(),
                        $item->getEmailChangeGrantId(),
                        $at
                    ),
                    default => throw new LogicException('Unsupported expiry purpose.')
                };
                $this->commands->execute($command);
                $report['dispatched']++;
            }
            $report['pages']++;
        }
    }
}
