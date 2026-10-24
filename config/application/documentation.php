<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Documentation\ReadPrivateOpenApiAction;
use App\Adapter\Http\Action\Documentation\ReadSwaggerBootstrapAction;
use App\Adapter\Http\Documentation\PrivateOpenApi;
use App\Adapter\Http\Documentation\PrivateOpenApiResponder;
use App\Adapter\Http\Documentation\SwaggerBootstrapResponder;
use App\Adapter\Http\Web\PublicRuntimeConfiguration;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(PrivateOpenApi::class, static fn(): PrivateOpenApi => new PrivateOpenApi(
        dirname(__DIR__, 2).'/var/openapi/openapi.json'
    ));
    $container->set(PrivateOpenApiResponder::class, static fn(Container $c): PrivateOpenApiResponder =>
        new PrivateOpenApiResponder($c->get(JSendResponseFactory::class)));
    $container->set(SwaggerBootstrapResponder::class, static fn(Container $c): SwaggerBootstrapResponder =>
        new SwaggerBootstrapResponder($c->get(JSendResponseFactory::class)));
    $container->set(ReadSwaggerBootstrapAction::class, static fn(Container $c): ReadSwaggerBootstrapAction =>
        new ReadSwaggerBootstrapAction(
            $c->get(PublicRuntimeConfiguration::class),
            $c->get(SwaggerBootstrapResponder::class)
        ));
    $container->set(ReadPrivateOpenApiAction::class, static fn(Container $c): ReadPrivateOpenApiAction =>
        new ReadPrivateOpenApiAction(
            $c->get(PublicRuntimeConfiguration::class),
            $c->get(PrivateOpenApi::class),
            $c->get(PrivateOpenApiResponder::class)
        ));
};
