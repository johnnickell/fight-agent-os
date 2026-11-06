<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Class FailureSchemas
 *
 * Describes deliberate Common refusal and server-error representations
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
                        or cookie. Declared input sources support dotted snake_case paths
                        such as body.email and body.mode on invitation issuance.
                        OpenAPI 3.0 cannot constrain these keys.
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
    description: 'Expected 4xx refusal; the historical schema name does not select an error envelope',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['fail']),
        new OA\Property(property: 'data', required: ['message'], properties: [
            new OA\Property(property: 'message', type: 'string', enum: [
                'Invalid input.', 'Authentication required.', 'Permission denied.', 'Resource not found.',
                'Method not allowed.', 'State conflict.', 'Resource gone.', 'Precondition failed.',
                'Not acceptable.', 'Request too large.', 'Unsupported media type.', 'Rule violation.',
                'Too many requests.'
            ])
        ], type: 'object', additionalProperties: false)
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(schema: 'BadRequestError', allOf: [
    new OA\Schema(ref: '#/components/schemas/Error'),
    new OA\Schema(properties: [new OA\Property(property: 'data', properties: [
        new OA\Property(property: 'message', enum: ['Invalid input.'])
    ])])
])]
#[OA\Schema(schema: 'UnauthorizedError', allOf: [
    new OA\Schema(ref: '#/components/schemas/Error'),
    new OA\Schema(properties: [new OA\Property(property: 'data', properties: [
        new OA\Property(property: 'message', enum: ['Authentication required.'])
    ])])
])]
#[OA\Schema(schema: 'ForbiddenError', allOf: [
    new OA\Schema(ref: '#/components/schemas/Error'),
    new OA\Schema(properties: [new OA\Property(property: 'data', properties: [
        new OA\Property(property: 'message', enum: ['Permission denied.'])
    ])])
])]
#[OA\Schema(schema: 'InternalError', required: ['status', 'message'], properties: [
    new OA\Property(property: 'status', type: 'string', enum: ['error']),
    new OA\Property(property: 'message', type: 'string', enum: ['Internal server error.'])
], type: 'object', additionalProperties: false)]
#[OA\Schema(schema: 'UnavailableError', required: ['status', 'message'], properties: [
    new OA\Property(property: 'status', type: 'string', enum: ['error']),
    new OA\Property(property: 'message', type: 'string', enum: [
        'Service unavailable.',
        'Recovery outcome uncertain. Read invitation status before another operation.'
    ])
], type: 'object', additionalProperties: false)]
#[OA\Schema(schema: 'NotFoundError', allOf: [
    new OA\Schema(ref: '#/components/schemas/Error'),
    new OA\Schema(properties: [new OA\Property(property: 'data', properties: [
        new OA\Property(property: 'message', enum: ['Resource not found.'])
    ])])
])]
#[OA\Schema(schema: 'MethodNotAllowedError', allOf: [
    new OA\Schema(ref: '#/components/schemas/Error'),
    new OA\Schema(properties: [new OA\Property(property: 'data', properties: [
        new OA\Property(property: 'message', enum: ['Method not allowed.'])
    ])])
])]
#[OA\Response(
    response: 'BadRequest',
    description: <<<'TEXT'
        Bootstrap body/content-type or ambiguous cookie input gives JSend fail.
        A query string gives the fixed Invalid input. JSend fail. No malformed-JSON POST is claimed.
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
    response: 'PublicValidationBadRequest',
    description: 'Invalid form name, forbidden body, Content-Type or query input',
    headers: [
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(oneOf: [
        new OA\Schema(ref: '#/components/schemas/ValidationFail'),
        new OA\Schema(ref: '#/components/schemas/BadRequestError')
    ])
)]
#[OA\Response(
    response: 'Unauthorized',
    description: 'Missing, invalid or no longer authoritative Bearer authentication',
    headers: [
        new OA\Header(
            header: 'WWW-Authenticate',
            required: true,
            schema: new OA\Schema(type: 'string', enum: ['Bearer'])
        ),
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'Exact required permission absent or HTTPS/origin/Fetch Metadata/preflight rejection',
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
    description: 'Unsupported method on a known API path',
    headers: [
        new OA\Header(header: 'Allow', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(ref: '#/components/schemas/MethodNotAllowedError')
)]
#[OA\Response(
    response: 'ServiceUnavailable',
    description: 'Declared temporary unavailability; retry hints do not authorize replay or imply rollback',
    headers: [
        new OA\Header(header: 'Retry-After', schema: new OA\Schema(type: 'integer', minimum: 0)),
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(ref: '#/components/schemas/UnavailableError')
)]
final class FailureSchemas
{
}
