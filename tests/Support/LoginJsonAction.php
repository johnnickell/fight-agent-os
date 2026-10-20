<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Adapter\Http\Attribute\JsonBody;
use Fight\Common\Application\Attribute\Validation;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Class LoginJsonAction
 *
 * Exposes only a test-local dispatch marker for the real source-validation pipeline
 */
final class LoginJsonAction
{
    public static int $calls = 0;

    /**
     * Records a dispatch after all controls passed
     */
    #[JsonBody]
    #[Validation(rules: [
        ['field' => 'email', 'label' => 'Email', 'rules' => 'required|type[string]'],
        ['field' => 'password', 'label' => 'Password', 'rules' => 'required|type[string]'],
        ['field' => 'remember', 'label' => 'Remember', 'rules' => 'required|type[bool]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++self::$calls;

        return new ResponseFactory()->createResponse(204);
    }
}
