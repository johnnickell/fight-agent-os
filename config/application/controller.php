<?php

declare(strict_types=1);

use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Action\Api\V1\Validations\ReadValidationAction;
use App\Adapter\Http\Action\ClientShellAction;
use App\Adapter\Http\Action\IndexAction;
use App\Adapter\Http\Middleware\Api\Validation\ApiInputValidation;
use App\Adapter\Http\Responder\Api\V1\Auth\CsrfBootstrapResponder;
use App\Adapter\Http\Responder\Api\V1\Validations\ReadValidationResponder;
use App\Adapter\Http\Web\ClientAssetManifest;
use App\Adapter\Http\Web\ClientShellResponder;
use App\Adapter\Validation\Catalog;
use App\Adapter\Validation\PublicForms;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Service\Container;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

return static function (Container $container): void {
    $container->set(ClientShellResponder::class, static function (): ClientShellResponder {
        $templates = new FilesystemLoader();
        $directory = dirname(__DIR__, 2).'/templates/client';
        // Missing deployments fail inside the responder, not during container construction.
        if (is_dir($directory)) {
            $templates->addPath($directory, 'client');
        }

        // Keep public rendering isolated from general-purpose template globals and options.
        return new ClientShellResponder(new Environment($templates, [
            'autoescape'       => 'html',
            'strict_variables' => true
        ]));
    });
    $container->set(ClientShellAction::class, static function (Container $container): ClientShellAction {
        return new ClientShellAction(
            new ClientAssetManifest($container['app.public_dir'].'/build'),
            $container->get(ClientShellResponder::class)
        );
    });
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
    $container->set(Catalog::class, static function (): Catalog {
        return new Catalog(
            dirname(__DIR__, 2).'/.runs/validations/catalog.json',
            array_column(PublicForms::registrations(), 'name')
        );
    });
    $container->set(ReadValidationResponder::class, static function (Container $container): ReadValidationResponder {
        return new ReadValidationResponder($container->get(JSendResponseFactory::class));
    });
    $container->set(ReadValidationAction::class, static function (Container $container): ReadValidationAction {
        return new ReadValidationAction(
            $container->get(Catalog::class),
            $container->get(ReadValidationResponder::class)
        );
    });
};
