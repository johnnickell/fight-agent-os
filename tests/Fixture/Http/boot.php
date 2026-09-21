<?php

declare(strict_types=1);

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

/** @var App $app */
$app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 3));

/** @var ContainerInterface $container */
$container = $app->getContainer();

$app->post(
    '/api/echo',
    function (ServerRequestInterface $request) use ($container): ResponseInterface {
        $body = $request->getParsedBody();

        return $container->get(JSendResponseFactory::class)->fromEnvelope(
            JSendEnvelope::success(new ApplicationData(is_array($body) ? $body : [])),
            200
        );
    }
)->setName('api.echo');

$app->post('/api/fail', function (): never {
    throw new RuntimeException('The HTTP journey failed.');
});

return $app;
