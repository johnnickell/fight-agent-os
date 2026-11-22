<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\V1\Authority\RoleMutationInput;
use App\Adapter\Http\Api\V1\Users\LifecycleInput;
use App\Adapter\Http\Attribute\JsonBody;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Validation\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;

/**
 * Protects field-specific mutation failures without echoing rejected inputs
 */
final class MutationInputTest extends TestCase
{
    /**
     * Refuses to treat a body identity as the current actor
     */
    public function testBodyCannotAuthenticate(): void
    {
        $this->expectException(AuthenticationRequired::class);
        LifecycleInput::authentication($this->request()->withAttribute(JsonBody::class, ['actor' => 'claimed']));
    }

    /**
     * Maps malformed or unconfirmed values to their exact public field errors
     */
    #[DataProvider('rejections')]
    public function testPublicFieldError(string $field, string $message): void
    {
        $request = $this->request()->withAttribute(JsonBody::class, ['confirm' => $field !== 'confirm']);
        try {
            match ($field) {
                'confirm', 'user_id' => LifecycleInput::target($request),
                'role_id' => RoleMutationInput::id($request),
                'expected_revision' => RoleMutationInput::revision('private input'),
                'name' => RoleMutationInput::name('private input'),
                default => throw new \LogicException('Unknown field.')
            };
            self::fail('Expected invalid input to be rejected.');
        } catch (ValidationException $failure) {
            self::assertSame([$field => [$message]], $failure->getErrors());
        }
    }

    /**
     * Supplies the only field name and safe message the caller receives
     *
     * @return iterable<string, array{string, string}>
     */
    public static function rejections(): iterable
    {
        yield 'confirmation' => ['confirm', 'Explicit confirmation is required.'];
        foreach (['user_id', 'role_id', 'expected_revision', 'name'] as $field) {
            yield $field => [$field, 'Invalid value.'];
        }
    }

    /**
     * Creates a request with an unusable route identifier
     */
    private function request(): ServerRequestInterface
    {
        $route = $this->createStub(RouteInterface::class);
        $route->method('getArgument')->willReturn('private input');

        return new ServerRequestFactory()->createServerRequest('POST', '/')
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createStub(RoutingResults::class));
    }
}
