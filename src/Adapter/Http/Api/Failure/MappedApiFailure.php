<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Failure;

use Fight\Common\Application\Http\JSend\JSendEnvelope;

/**
 * Class MappedApiFailure
 *
 * Holds an allowlisted public failure without exposing exception details
 */
final readonly class MappedApiFailure
{
    /**
     * Constructs MappedApiFailure
     *
     * @param integer $status
     * @param JSendEnvelope $envelope
     * @param array<string, string> $headers
     */
    public function __construct(public int $status, public JSendEnvelope $envelope, public array $headers = [])
    {
    }
}
