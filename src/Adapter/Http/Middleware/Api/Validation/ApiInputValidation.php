<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Validation;

use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Http\Api\Validation\InputFailures;
use App\Adapter\Http\Attribute\JsonBody;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Application\Validation\ValidationService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Slim\Routing\RouteContext;

/**
 * Class ApiInputValidation
 *
 * Validates matched API input before resolving or invoking its Action
 */
final readonly class ApiInputValidation implements MiddlewareInterface
{
    /**
     * Constructs ApiInputValidation
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $action = RouteContext::fromRequest($request)->getRoute()?->getCallable();
        if (!is_string($action) || !class_exists($action)) {
            throw new \LogicException('An API route requires a declared Action.');
        }

        $method = new ReflectionMethod($action, '__invoke');
        $bodyDeclaration = $method->getAttributes(JsonBody::class)[0] ?? null;
        if ($bodyDeclaration === null) {
            if ((string) $request->getBody() !== '' || $request->hasHeader('Content-Type')) {
                return $this->reject(['body' => ['Body is not allowed.']]);
            }

            if ($action === CsrfBootstrapAction::class) {
                $cookies = $request->getHeader('Cookie');
                if (count($cookies) > 1 || strlen($cookies[0] ?? '') > 4096) {
                    return $this->reject(['cookie' => ['Invalid cookie.']]);
                }

                $found = false;
                foreach (explode(';', $cookies[0] ?? '') as $cookie) {
                    $pair = explode('=', trim($cookie), 2);
                    if ($pair[0] !== CsrfCookie::NAME) {
                        continue;
                    }

                    if ($found || count($pair) !== 2) {
                        return $this->reject(['cookie' => ['Ambiguous cookie.']]);
                    }

                    $found = true;
                }
            }

            return $handler->handle($request);
        }

        /** @var JsonBody $body */
        $body = $bodyDeclaration->newInstance();
        if (!class_exists($body->dto)) {
            throw new \LogicException('The declared request DTO is unavailable.');
        }

        if (!preg_match('/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/i', $request->getHeaderLine('Content-Type'))) {
            return $this->reject(['body' => ['Expected JSON content type.']]);
        }

        if (($request->getBody()->getSize() ?? 0) > 65536) {
            return $this->reject(['body' => ['Body is too large.']]);
        }

        $raw = (string) $request->getBody();
        if (strlen($raw) > 65536) {
            return $this->reject(['body' => ['Body is too large.']]);
        }

        if ($raw === '') {
            return $this->reject(['body' => ['Body is required.']]);
        }

        try {
            $decoded = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->reject(['body' => ['Invalid JSON.']]);
        }

        if (!$decoded instanceof \stdClass || $this->hasDuplicateKeys($raw)) {
            return $this->reject(['body' => ['Expected a JSON object with unique fields.']]);
        }

        $dto = new ReflectionClass($body->dto);
        $constructor = $dto->getConstructor();
        if ($constructor === null || !$constructor->isPublic()) {
            throw new \LogicException('A request DTO requires a public constructor.');
        }

        /** @var array<string, mixed> $input */
        $input = (array) $decoded;
        $fields = [];
        $allowed = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            if (
                !preg_match('/\A[a-z][a-zA-Z0-9]*\z/', $name) || !$type instanceof ReflectionNamedType
                || !$type->isBuiltin() || !in_array($type->getName(), ['string', 'int', 'float', 'bool'], true)
            ) {
                throw new \LogicException('Request DTO fields must declare supported primitive transport types.');
            }

            $wireName = strtolower((string) preg_replace('/[A-Z]/', '_$0', $name));
            $allowed[$wireName] = true;
            if (!array_key_exists($wireName, $input)) {
                if (!$parameter->isOptional()) {
                    $fields['body.'.$wireName] = ['Field is required.'];
                }

                continue;
            }

            $value = $input[$wireName];
            $valid = $value === null ? $type->allowsNull() : match ($type->getName()) {
                'string' => is_string($value),
                'int' => is_int($value),
                'float' => is_float($value) || is_int($value),
                'bool' => is_bool($value),
            };
            if (!$valid) {
                $fields['body.'.$wireName] = ['Invalid type.'];
            }
        }

        if (array_diff_key($input, $allowed) !== []) {
            $fields['body'] = ['Undeclared field.'];
        }

        if ($fields !== []) {
            return $this->reject($fields);
        }

        $declaration = $method->getAttributes(Validation::class)[0] ?? null;
        if ($declaration !== null) {
            /** @var Validation $validation */
            $validation = $declaration->newInstance();
            try {
                (new ValidationService())->validate($input, $validation->rules());
            } catch (ValidationException $exception) {
                $failures = [];
                foreach ($exception->getErrors() as $field => $messages) {
                    // Never return package prose or untrusted rule labels; only declared paths.
                    if (
                        preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*|\.[0-9]+)*\z/', $field)
                        && isset($allowed[explode('.', $field, 2)[0]])
                    ) {
                        $failures['body.'.$field] = array_fill(0, count($messages), 'Invalid value.');
                    }
                }

                return $this->reject($failures === [] ? ['body' => ['Invalid input.']] : $failures);
            }
        }

        // Actions consume this checked input and construct their own transport DTO.
        return $handler->handle($request->withAttribute(JsonBody::class, $input));
    }

    /**
     * Detects repeated object keys without exposing raw payload or parser detail
     *
     * Nested containers are refused by the DTO type check; scan their keys too
     * so any future container support cannot silently adopt last-key-wins.
     */
    private function hasDuplicateKeys(string $raw): bool
    {
        $stack = [];
        $length = strlen($raw);
        for ($offset = 0; $offset < $length; ++$offset) {
            $char = $raw[$offset];
            if ($char === '"') {
                $start = $offset++;
                while ($offset < $length) {
                    if ($raw[$offset] === '\\') {
                        $offset += 2;
                        continue;
                    }
                    if ($raw[$offset] === '"') {
                        break;
                    }
                    ++$offset;
                }

                $end = $offset;
                $next = $end + 1;
                while ($next < $length && ctype_space($raw[$next])) {
                    ++$next;
                }
                $top = count($stack) - 1;
                if ($top >= 0 && $stack[$top] !== null && ($raw[$next] ?? '') === ':') {
                    $key = json_decode(substr($raw, $start, $end - $start + 1), true, 64, JSON_THROW_ON_ERROR);
                    if (isset($stack[$top][$key])) {
                        return true;
                    }
                    $stack[$top][$key] = true;
                }
            } elseif ($char === '{') {
                $stack[] = [];
            } elseif ($char === '[') {
                $stack[] = null;
            } elseif ($char === '}' || $char === ']') {
                array_pop($stack);
            }
        }

        return false;
    }

    /**
     * Returns a deterministic sanitized non-cacheable JSend failure
     *
     * @param array<string, list<string>> $fields
     */
    private function reject(array $fields): ResponseInterface
    {
        ksort($fields);
        foreach ($fields as &$messages) {
            $messages = array_values(array_unique($messages));
        }

        return $this->responses->fromEnvelope(
            JSendEnvelope::fail(new InputFailures($fields)),
            400,
            ['Cache-Control' => 'no-store']
        );
    }
}
