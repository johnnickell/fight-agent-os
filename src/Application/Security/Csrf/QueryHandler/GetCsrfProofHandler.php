<?php

declare(strict_types=1);

namespace App\Application\Security\Csrf\QueryHandler;

use App\Application\Security\Csrf\Clock\CsrfClock;
use App\Application\Security\Csrf\Service\CsrfNonceGenerator;
use App\Application\Security\Csrf\Service\CsrfProofs;
use App\Domain\Security\Csrf\CsrfProof;
use App\Domain\Security\Csrf\Query\CsrfProofView;
use App\Domain\Security\Csrf\Query\GetCsrfProof;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use InvalidArgumentException;

/**
 * Class GetCsrfProofHandler
 *
 * Issues stateless CSRF proofs without modifying business state
 */
final readonly class GetCsrfProofHandler implements QueryHandler
{
    /**
     * Constructs GetCsrfProofHandler
     */
    public function __construct(
        private CsrfNonceGenerator $nonces,
        private CsrfClock $clock,
        private CsrfProofs $proofs
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return GetCsrfProof::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): CsrfProofView
    {
        $query = $queryMessage->payload();
        if (!$query instanceof GetCsrfProof) {
            throw new InvalidArgumentException('Unexpected CSRF query.');
        }

        $newNonce = $query->nonce === null;
        $nonce = $query->nonce ?? $this->nonces->generate();
        $expiresAt = $this->clock->now() + 900;

        return new CsrfProofView(
            $nonce,
            new CsrfProof($this->proofs->sign($nonce, $expiresAt), $expiresAt),
            $newNonce
        );
    }
}
