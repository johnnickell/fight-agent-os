<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Failure;

use Fight\Common\Domain\Type\Arrayable;

/**
 * Class FailureData
 *
 * Presents deliberate direct refusals without using private diagnostic codes
 */
final readonly class FailureData implements Arrayable
{
    /**
     * Constructs FailureData
     */
    public function __construct(private string $message)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['message' => $this->message];
    }
}
