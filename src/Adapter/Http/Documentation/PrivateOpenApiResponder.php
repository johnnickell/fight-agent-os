<?php

declare(strict_types=1);

namespace App\Adapter\Http\Documentation;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PrivateOpenApiResponder
 *
 * Presents the complete private JSON without a download or caching bypass
 */
final readonly class PrivateOpenApiResponder
{
    /**
     * Constructs PrivateOpenApiResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Reports unavailable private runtime data without disclosing parser or filesystem detail
     */
    public function unavailable(): ResponseInterface
    {
        return $this->responses->fromEnvelope(JSendEnvelope::error('Internal server error.'), 503, [
            'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'
        ]);
    }

    /**
     * Returns an already authorized document
     */
    public function respond(ResponseInterface $response, string $document): ResponseInterface
    {
        $response->getBody()->write($document);

        return $response->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }
}
