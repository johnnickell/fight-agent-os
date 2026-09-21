<?php

declare(strict_types=1);

namespace Tests\Fixture\Messaging;

use Fight\Common\Domain\Messaging\Query\Query;

final readonly class ReadJourneyQuery implements Query
{
    public function __construct(public string $value)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self((string) $data['value']);
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
