<?php

declare(strict_types=1);

namespace App\Adapter\Http\Documentation;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class SwaggerBootstrapResponder
 *
 * Presents only the protected viewer protocol, never credentials or operator configuration
 */
final readonly class SwaggerBootstrapResponder
{
    /**
     * Constructs SwaggerBootstrapResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns the authenticated viewer initialization
     */
    public function respond(): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new SwaggerBootstrapData()),
            200,
            ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
