<?php

declare(strict_types=1);

namespace App\Adapter\Http\Middleware\Api\Validation;

use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Http\Api\Validation\InputFailures;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\QueryString;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Application\Validation\RulesParser;
use Fight\Common\Application\Validation\ValidationService;
use JsonException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use Slim\Routing\RouteContext;
use stdClass;
use Throwable;

/**
 * Class ApiInputValidation
 *
 * Validates one declared API input source before invoking its Action
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
        if (!is_string($action) || !str_ends_with($action, ':handle')) {
            throw new LogicException('An API route requires a declared Action handle method.');
        }
        $class = substr($action, 0, -strlen(':handle'));
        if (!class_exists($class) || !method_exists($class, 'handle')) {
            throw new LogicException('An API route requires a declared Action handle method.');
        }
        $method = new ReflectionMethod($class, 'handle');
        if (!$method->isPublic()) {
            throw new LogicException('An API route requires a public Action handle method.');
        }
        $body = $method->getAttributes(JsonBody::class);
        $query = $method->getAttributes(QueryString::class);
        $declarations = $method->getAttributes(Validation::class);
        if (count($body) + count($query) > 1 || count($declarations) > 1) {
            throw new LogicException('Ambiguous API input declaration.');
        }

        $source = $body !== [] ? JsonBody::class : ($query !== [] ? QueryString::class : null);
        if ($source !== null) {
            $marker = $body[0] ?? $query[0];
            try {
                if ($marker->getArguments() !== []) {
                    throw new LogicException('An API input source takes no arguments.');
                }
                $marker->newInstance();
            } catch (Throwable $exception) {
                throw new LogicException('Invalid API input source declaration.', 0, $exception);
            }
        }
        if (($source === null) !== ($declarations === [])) {
            throw new LogicException('A source and its Validation declaration must occur together.');
        }

        $verb = $request->getMethod();
        $validMethod = match ($source) {
            QueryString::class => $verb === 'GET',
            JsonBody::class => in_array($verb, ['POST', 'PUT', 'PATCH'], true),
            default => true,
        };
        if (!$validMethod) {
            throw new LogicException('Unsupported API input source and method.');
        }

        $allowed = [];
        $rules = [];
        if ($declarations !== []) {
            try {
                /** @var Validation $validation */
                $validation = $declarations[0]->newInstance();
                $rules = $validation->rules();
                $parsed = RulesParser::parse($rules);
                if (count($parsed) !== count($rules)) {
                    throw new LogicException('Repeated API field declaration.');
                }
                foreach ($parsed as $field => $fieldRules) {
                    if (!preg_match('/\A[a-z][a-z0-9_]*\z/D', $field)) {
                        throw new LogicException('Invalid API field declaration.');
                    }
                    $allowed[$field] = true;
                    if ($fieldRules === []) {
                        throw new LogicException('Empty API field rules.');
                    }
                }
                // Also check package rule arity and argument types before examining user input.
                (new ValidationService())->validate([], $rules);
            } catch (ValidationException) {
                // A required field may fail for the empty qualification input.
            } catch (Throwable $exception) {
                throw new LogicException('Invalid API validation declaration.', 0, $exception);
            }
        }

        $bodySize = $request->getBody()->getSize();
        if ($source !== JsonBody::class && $bodySize !== null && $bodySize > 0) {
            return $this->reject(['body' => ['Body is not allowed.']]);
        }
        if ($source === JsonBody::class && $bodySize !== null && $bodySize > 65536) {
            return $this->reject(['body' => ['Body is too large.']]);
        }
        // The limit also applies when the stream cannot report its size.
        if ($request->getBody()->isSeekable()) {
            $request->getBody()->rewind();
        }
        $raw = '';
        while (!$request->getBody()->eof() && strlen($raw) < 65537) {
            $part = $request->getBody()->read(65537 - strlen($raw));
            if ($part === '') {
                break;
            }
            $raw .= $part;
        }
        if ($source === JsonBody::class && strlen($raw) > 65536) {
            return $this->reject(['body' => ['Body is too large.']]);
        }
        if ($source !== JsonBody::class && ($raw !== '' || $request->hasHeader('Content-Type'))) {
            return $this->reject(['body' => ['Body is not allowed.']]);
        }
        if (
            $source !== QueryString::class && $request->getUri()->getQuery() !== ''
            && $class !== CsrfBootstrapAction::class
        ) {
            return $this->reject(['query' => ['Query is not allowed.']]);
        }

        if ($source === null) {
            if ($class === CsrfBootstrapAction::class) {
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

        if ($source === JsonBody::class) {
            if ($request->getUri()->getQuery() !== '') {
                return $this->reject(['query' => ['Query is not allowed.']]);
            }
            if (
                !preg_match(
                    '/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/i',
                    $request->getHeaderLine('Content-Type')
                )
            ) {
                return $this->reject(['body' => ['Expected JSON content type.']]);
            }
            if ($raw === '') {
                return $this->reject(['body' => ['Body is required.']]);
            }
            try {
                $decoded = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->reject(['body' => ['Invalid JSON.']]);
            }
            if (!$decoded instanceof stdClass || $this->hasDuplicateKeys($raw)) {
                return $this->reject(['body' => ['Expected a JSON object with unique fields.']]);
            }
            /** @var array<string, mixed> $input */
            $input = (array) $decoded;
        } else {
            $rawQuery = $request->getUri()->getQuery();
            $originalUri = $request->getServerParams()['REQUEST_URI'] ?? null;
            if (is_string($originalUri)) {
                $originalQuery = str_contains($originalUri, '?') ? explode('?', $originalUri, 2)[1] : '';
                // PSR URI implementations may re-encode malformed percent triplets.
                if ($request->getUri()->withQuery($originalQuery)->getQuery() !== $rawQuery) {
                    return $this->reject(['query' => ['Invalid query.']]);
                }
                $rawQuery = $originalQuery;
            }
            $input = $this->parseQuery($rawQuery);
            if ($input === null) {
                return $this->reject(['query' => ['Invalid query.']]);
            }
        }

        $location = $source === JsonBody::class ? 'body' : 'query';
        if (array_diff_key($input, $allowed) !== []) {
            return $this->reject([$location => ['Undeclared field.']]);
        }
        foreach ($input as $field => $value) {
            if (!is_scalar($value) && $value !== null) {
                return $this->reject([$location.'.'.$field => ['Invalid type.']]);
            }
        }

        try {
            (new ValidationService())->validate($input, $rules);
        } catch (ValidationException $exception) {
            $failures = [];
            foreach ($exception->getErrors() as $field => $messages) {
                if (isset($allowed[$field])) {
                    $failures[$location.'.'.$field] = array_fill(0, min(count($messages), 8), 'Invalid value.');
                }
            }

            return $this->reject($failures === [] ? [$location => ['Invalid input.']] : $failures);
        }

        return $handler->handle($request->withAttribute($source, $input));
    }

    /**
     * Parses flat query fields before PHP can normalize or discard ambiguous names
     *
     * @return array<string, string>|null
     */
    private function parseQuery(string $raw): ?array
    {
        if (strlen($raw) > 4096) {
            return null;
        }
        if ($raw === '') {
            return [];
        }
        $parts = explode('&', $raw);
        if (count($parts) > 32) {
            return null;
        }
        $input = [];
        foreach ($parts as $part) {
            if ($part === '' || substr_count($part, '=') > 1) {
                return null;
            }
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            if (!$this->validEncoding($key) || !$this->validEncoding($value)) {
                return null;
            }
            $key = urldecode($key);
            $value = urldecode($value);
            if (
                !preg_match('/\A[a-z][a-z0-9_]*\z/D', $key) || array_key_exists($key, $input)
                || !preg_match('//u', $value) || str_contains($value, "\0")
            ) {
                return null;
            }
            $input[$key] = $value;
        }

        return $input;
    }

    /**
     * Checks percent triplets before decoding a query component
     */
    private function validEncoding(string $value): bool
    {
        return preg_match('/%(?![0-9a-fA-F]{2})/', $value) === 0 && preg_match('//u', urldecode($value)) === 1;
    }

    /**
     * Detects repeated JSON object keys at every nesting depth
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
