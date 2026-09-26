<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware;

use Fight\Common\Application\Http\JSend\JSendEnvelope;

/**
 * Class FailureClassification
 *
 * Holds an allowlisted public failure without exposing exception details
 */
final readonly class FailureClassification
{
    /**
     * Constructs FailureClassification
     */
    public function __construct(public int $status, public JSendEnvelope $envelope)
    {
    }
}
