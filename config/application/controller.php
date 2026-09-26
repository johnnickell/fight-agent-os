<?php

declare(strict_types=1);

use App\Adapter\Http\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Api\V1\Auth\CsrfBootstrapResponder;
use App\Adapter\Http\IndexAction;
use App\Adapter\Http\Middleware\Api\ApiInputValidation;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(IndexAction::class, static function (): IndexAction {
        return new IndexAction();
    });
    $container->set(ApiInputValidation::class, static function (Container $container): ApiInputValidation {
        return new ApiInputValidation($container->get(JSendResponseFactory::class));
    });
    $container->set(CsrfBootstrapResponder::class, static function (Container $container): CsrfBootstrapResponder {
        return new CsrfBootstrapResponder($container->get(JSendResponseFactory::class));
    });
    $container->set(CsrfBootstrapAction::class, static function (Container $container): CsrfBootstrapAction {
        return new CsrfBootstrapAction(
            $container->get(QueryBus::class),
            $container->get(CsrfBootstrapResponder::class)
        );
    });
};
