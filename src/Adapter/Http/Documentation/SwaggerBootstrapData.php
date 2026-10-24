<?php

declare(strict_types=1);

namespace App\Adapter\Http\Documentation;

use Fight\Common\Domain\Type\Arrayable;

/**
 * Class SwaggerBootstrapData
 *
 * Projects only the bounded viewer protocol
 */
final readonly class SwaggerBootstrapData implements Arrayable
{
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['viewer_version' => 1, 'document_path' => '/api/v1/documentation/openapi'];
    }
}
