<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1;

use Fight\Common\Domain\Type\Arrayable;

/**
 * Class InputFailures
 *
 * Presents only stable field paths and safe transport messages
 */
final readonly class InputFailures implements Arrayable
{
    /**
     * Constructs InputFailures
     *
     * @param array<string, list<string>> $fields
     */
    public function __construct(private array $fields)
    {
    }

    /**
     * Returns the field-to-message-list failure representation
     *
     * @return array{fields: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return ['fields' => $this->fields];
    }
}
