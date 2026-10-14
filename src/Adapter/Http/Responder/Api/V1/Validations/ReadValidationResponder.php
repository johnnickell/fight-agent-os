<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Validations;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ReadValidationResponder
 *
 * Presents one approved public form without mutable cache or cookies
 */
#[OA\Schema(
    schema: 'PublicValidationSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            type: 'object',
            required: ['schema_version', 'revision', 'form_name', 'fields'],
            properties: [
                new OA\Property(property: 'schema_version', type: 'integer', enum: [1]),
                new OA\Property(property: 'revision', type: 'string', pattern: '^[a-f0-9]{64}$'),
                new OA\Property(property: 'form_name', type: 'string', pattern: '^[a-z][a-z0-9_]{0,63}$'),
                new OA\Property(
                    property: 'fields',
                    type: 'object',
                    minProperties: 1,
                    additionalProperties: new OA\AdditionalProperties(
                        type: 'object',
                        required: ['client_field', 'rules'],
                        properties: [
                            new OA\Property(
                                property: 'client_field',
                                type: 'string',
                                pattern: '^[a-z][a-zA-Z0-9]{0,63}$'
                            ),
                            new OA\Property(
                                property: 'rules',
                                type: 'array',
                                minItems: 1,
                                items: new OA\Items(
                                    type: 'object',
                                    required: ['type', 'args', 'message', 'depends_on'],
                                    properties: [
                                        new OA\Property(
                                            property: 'type',
                                            type: 'string',
                                            enum: ['Required', 'Type', 'MinLength', 'MaxLength', 'Same']
                                        ),
                                        new OA\Property(
                                            property: 'args',
                                            type: 'array',
                                            items: new OA\Items(type: 'string')
                                        ),
                                        new OA\Property(
                                            property: 'message',
                                            type: 'string',
                                            minLength: 1,
                                            maxLength: 200
                                        ),
                                        new OA\Property(
                                            property: 'depends_on',
                                            type: 'array',
                                            items: new OA\Items(type: 'string')
                                        )
                                    ],
                                    additionalProperties: false
                                )
                            )
                        ],
                        additionalProperties: false
                    )
                )
            ],
            additionalProperties: false
        )
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class ReadValidationResponder
{
    /**
     * Constructs ReadValidationResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Sends the versioned schema with no-store transport
     *
     * @param array{schema_version: int, revision: string, form_name: string, fields: array<string, mixed>} $schema
     */
    public function respond(array $schema): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new PublicValidationData($schema)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
