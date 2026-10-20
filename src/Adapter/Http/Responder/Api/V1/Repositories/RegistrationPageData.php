<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Repositories;

use App\Application\Workspace\RegistrationView;
use Fight\Common\Domain\Type\Arrayable;
use OpenApi\Attributes as OA;

/**
 * Class RegistrationPageData
 */
#[OA\Schema(
    schema: 'RepositoryRegistrationPageSuccess',
    type: 'object',
    additionalProperties: false,
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            type: 'object',
            additionalProperties: false,
            required: ['items', 'has_more', 'offset', 'limit'],
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    maxItems: 100,
                    items: new OA\Items(ref: '#/components/schemas/RepositoryRegistration')
                ),
                new OA\Property(property: 'has_more', type: 'boolean'),
                new OA\Property(property: 'offset', type: 'integer', minimum: 0, maximum: 100000),
                new OA\Property(property: 'limit', type: 'integer', minimum: 1, maximum: 100)
            ]
        )
    ]
)]
final readonly class RegistrationPageData implements Arrayable
{
    /**
     * Constructs RegistrationPageData
     *
     * @param array{items: list<RegistrationView>, has_more: bool} $page
     */
    public function __construct(private array $page, private int $offset, private int $limit)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'items'    => array_map(static fn(RegistrationView $view): array => $view->data, $this->page['items']),
            'has_more' => $this->page['has_more'],
            'offset'   => $this->offset,
            'limit'    => $this->limit
        ];
    }
}
