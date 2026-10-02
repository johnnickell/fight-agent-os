<?php

declare(strict_types=1);

namespace App\Adapter\CredentialDelivery;

use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use InvalidArgumentException;

/**
 * Class InMemoryCredentialDeliveryProvider
 *
 * Simulates invitation and reset effects without retaining invocation material
 */
final class InMemoryCredentialDeliveryProvider implements CredentialDeliveryProvider
{
    /** @var list<array{purpose: string, idempotency_id: string, outcome: string}> */
    private array $attempts = [];
    /** @var array<string, CredentialDeliveryOutcome> */
    private readonly array $outcomes;

    /**
     * Constructs InMemoryCredentialDeliveryProvider
     *
     * @param array<string, mixed> $outcomes
     */
    public function __construct(array $outcomes = [])
    {
        $validated = [];
        foreach ($outcomes as $purpose => $outcome) {
            if (
                !in_array($purpose, ['activation', 'password_reset'], true)
                || !$outcome instanceof CredentialDeliveryOutcome
            ) {
                throw new InvalidArgumentException('Unsupported credential delivery outcome configuration.');
            }
            $validated[$purpose] = $outcome;
        }
        $this->outcomes = $validated;
    }

    /**
     * @inheritDoc
     */
    public function deliver(CredentialDeliveryInvocation $invocation): CredentialDeliveryOutcome
    {
        $purpose = $invocation->getPurpose();
        if (
            !in_array($purpose, ['activation', 'password_reset'], true)
            || $invocation->getIdempotencyId() === ''
            || $invocation->getCredential() === ''
        ) {
            throw new InvalidArgumentException('Unsupported credential delivery invocation.');
        }

        $outcome = $this->outcomes[$purpose] ?? CredentialDeliveryOutcome::RETRYABLE_FAILURE;
        $this->attempts[] = [
            'purpose'        => $purpose,
            'idempotency_id' => $invocation->getIdempotencyId(),
            'outcome'        => $outcome->name
        ];

        return $outcome;
    }

    /**
     * Returns only secret-free attempt identities and typed outcomes
     *
     * @return list<array{purpose: string, idempotency_id: string, outcome: string}>
     */
    public function attempts(): array
    {
        return $this->attempts;
    }
}
