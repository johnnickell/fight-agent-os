<?php

declare(strict_types=1);

namespace Tests\Fixture\Serialization;

use Fight\Common\Domain\Serialization\Serializable;

final readonly class JourneySerializable implements Serializable
{
    public function __construct(private string $value)
    {
    }

    /** @param array<string, mixed> $data */
    public static function arrayDeserialize(array $data): static
    {
        return new self((string) $data['value']);
    }

    /** @return array<string, string> */
    public function arraySerialize(): array
    {
        return ['value' => $this->value];
    }

    public function value(): string
    {
        return $this->value;
    }
}
