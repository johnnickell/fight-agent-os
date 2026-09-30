<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Class FailureSchemas
 *
 * Describes existing safe failure representations and mapper-only schemas
 */
#[OA\Schema(
    schema: 'ValidationFail',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['fail']),
        new OA\Property(
            property: 'data',
            required: ['fields'],
            properties: [
                new OA\Property(
                    property: 'fields',
                    description: <<<'TEXT'
                        Field paths map to unique nonempty message lists. Bootstrap uses body
                        or cookie. The existing DTO mapper supports dotted snake_case paths
                        such as body.profile_name; no body-bearing HTTP operation exists yet.
                        OpenAPI 3.0 cannot constrain these dictionary key names.
                        TEXT,
                    minProperties: 1,
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(
                        type: 'array',
                        items: new OA\Items(type: 'string', minLength: 1),
                        minItems: 1,
                        uniqueItems: true
                    )
                )
            ],
            type: 'object',
            additionalProperties: false
        )
    ],
    type: 'object',
    example: ['status' => 'fail', 'data' => ['fields' => ['body' => ['Body is not allowed.']]]],
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'BootstrapValidationFail',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/ValidationFail'),
        new OA\Schema(properties: [
            new OA\Property(property: 'data', properties: [
                new OA\Property(
                    property: 'fields',
                    maxProperties: 1,
                    minProperties: 1,
                    properties: [
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(type: 'string', enum: ['Body is not allowed.'])
                        ),
                        new OA\Property(
                            property: 'cookie',
                            type: 'array',
                            items: new OA\Items(type: 'string', enum: ['Invalid cookie.', 'Ambiguous cookie.'])
                        )
                    ],
                    type: 'object',
                    additionalProperties: false
                )
            ])
        ])
    ]
)]
#[OA\Schema(
    schema: 'Error',
    required: ['status', 'message'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['error']),
        new OA\Property(property: 'message', type: 'string', enum: [
            'Bad request.', 'Unauthorized.', 'Forbidden.', 'Not found.', 'Method not allowed.',
            'Conflict.', 'Gone.', 'Too many requests.', 'Internal server error.'
        ])
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(schema: 'BadRequestError', allOf: [
    new OA\Schema(ref: '#/components/schemas/Error'),
    new OA\Schema(properties: [new OA\Property(property: 'message', enum: ['Bad request.'])])
])]
#[OA\Schema(schema: 'ForbiddenError', allOf: [
    new OA\Schema(ref: '#/components/schemas/Error'),
    new OA\Schema(properties: [new OA\Property(property: 'message', enum: ['Forbidden.'])])
])]
#[OA\Schema(schema: 'InternalError', allOf: [
    new OA\Schema(ref: '#/components/schemas/Error'),
    new OA\Schema(properties: [new OA\Property(property: 'message', enum: ['Internal server error.'])])
])]
#[OA\Schema(schema: 'NotFoundError', allOf: [
    new OA\Schema(ref: '#/components/schemas/Error'),
    new OA\Schema(properties: [new OA\Property(property: 'message', enum: ['Not found.'])])
])]
#[OA\Schema(schema: 'MethodNotAllowedError', allOf: [
    new OA\Schema(ref: '#/components/schemas/Error'),
    new OA\Schema(properties: [new OA\Property(property: 'message', enum: ['Method not allowed.'])])
])]
#[OA\Response(
    response: 'BadRequest',
    description: <<<'TEXT'
        Bootstrap body/content-type or ambiguous cookie input gives JSend fail.
        A query string gives JSend error. No malformed-JSON POST is claimed.
        TEXT,
    headers: [
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(oneOf: [
        new OA\Schema(ref: '#/components/schemas/BootstrapValidationFail'),
        new OA\Schema(ref: '#/components/schemas/BadRequestError')
    ])
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'HTTPS/origin/Fetch Metadata/preflight rejection',
    headers: [
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')
)]
#[OA\Response(
    response: 'InternalError',
    description: <<<'TEXT'
        Unexpected downstream failure. No exception message, stack, internal path or
        secret is returned. Correlation is in the header only; logs use safe metadata.
        TEXT,
    headers: [
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(ref: '#/components/schemas/InternalError')
)]
#[OA\Response(
    response: 'NotFound',
    description: 'API routing miss, not an operation or a generic lookup-to-404 promise',
    headers: [
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')
)]
#[OA\Response(
    response: 'MethodNotAllowed',
    description: 'Unsupported method on a known API path; not a supported POST operation',
    headers: [
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(ref: '#/components/schemas/MethodNotAllowedError')
)]
final class FailureSchemas
{
}
