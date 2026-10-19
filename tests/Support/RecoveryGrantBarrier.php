<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrant;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class RecoveryGrantBarrier
 *
 * Coordinates real competing transactions after a package handler has captured its grant snapshot
 */
final readonly class RecoveryGrantBarrier implements ActivationGrantRepository
{
    /**
     * Constructs RecoveryGrantBarrier
     */
    public function __construct(private ActivationGrantRepository $inner, private Closure $observe)
    {
    }

    /**
     * @inheritDoc
     */
    public function getLatestByUserId(UserId $userId): ?ActivationGrant
    {
        $grant = $this->inner->getLatestByUserId($userId);
        ($this->observe)();

        return $grant;
    }

    /**
     * @inheritDoc
     */
    public function findDue(DateTimeImmutable $at, int $limit): array
    {
        return $this->inner->findDue($at, $limit);
    }

    /**
     * @inheritDoc
     */
    public function findExpired(DateTimeImmutable $at, int $limit): array
    {
        return $this->inner->findExpired($at, $limit);
    }

    /**
     * @inheritDoc
     */
    public function getById(ActivationGrantId $activationGrantId): ?ActivationGrant
    {
        return $this->inner->getById($activationGrantId);
    }

    /**
     * @inheritDoc
     */
    public function getByDeliveryId(ActivationDeliveryId $activationDeliveryId): ?ActivationGrant
    {
        return $this->inner->getByDeliveryId($activationDeliveryId);
    }

    /**
     * @inheritDoc
     */
    public function add(ActivationGrant $activationGrant): bool
    {
        return $this->inner->add($activationGrant);
    }

    /**
     * @inheritDoc
     */
    public function replace(ActivationGrant $predecessor, ActivationGrant $replacement): bool
    {
        return $this->inner->replace($predecessor, $replacement);
    }

    /**
     * @inheritDoc
     */
    public function replaceWithSuccessor(
        ActivationGrant $predecessor,
        ActivationGrant $terminalPredecessor,
        ActivationGrant $successor
    ): bool {
        return $this->inner->replaceWithSuccessor($predecessor, $terminalPredecessor, $successor);
    }

    /**
     * @inheritDoc
     */
    public function addSuccessor(ActivationGrant $successor): bool
    {
        return $this->inner->addSuccessor($successor);
    }
}
