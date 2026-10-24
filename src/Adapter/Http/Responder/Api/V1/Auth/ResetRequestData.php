<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use Fight\Common\Domain\Type\Arrayable;

/**
 * Class ResetRequestData
 *
 * Projects a fixed acknowledgement without identity or delivery evidence
 */
final readonly class ResetRequestData implements Arrayable
{
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['accepted' => true];
    }
}
