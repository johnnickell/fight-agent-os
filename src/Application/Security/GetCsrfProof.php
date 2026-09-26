<?php

declare(strict_types=1);

namespace App\Application\Security;

use Fight\Common\Domain\Messaging\Query\Query;
use InvalidArgumentException;

/**
 * Class GetCsrfProof
 *
 * Requests a proof for an optional validated nonce
 */
final readonly class GetCsrfProof implements Query
{
    /**
     * Constructs GetCsrfProof
     */
    public function __construct(public ?string $nonce)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        if (array_keys($data) !== ['nonce'] || ($data['nonce'] !== null && !is_string($data['nonce']))) {
            throw new InvalidArgumentException('Invalid CSRF query input.');
        }

        return new self($data['nonce']);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['nonce' => $this->nonce];
    }
}
