<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Http\Attribute\JsonBody;
use Fight\Common\Application\Attribute\Validation;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Class EmptyAuthJsonAction
 *
 * Exposes a test-local empty-object dispatch marker without a production authentication route
 */
final class EmptyAuthJsonAction
{
    public static int $calls = 0;

    /**
     * Records a dispatch after all controls passed
     */
    #[JsonBody]
    #[Validation(rules: [])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++self::$calls;

        return new ResponseFactory()->createResponse(204);
    }
}
