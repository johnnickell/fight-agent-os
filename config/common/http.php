<?php

declare(strict_types=1);

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient;
use Fight\Common\Adapter\ServiceContainer\Fight\ContainerCapabilityRegistrar;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Service\Container;
use GuzzleHttp\Client;
use Psr\Http\Factory\ResponseFactoryInterface;
use Psr\Http\Factory\ServerRequestFactoryInterface;
use Psr\Http\Factory\StreamFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

return static function (Container $container): void {
    $container->set(ResponseFactoryInterface::class, static function (): ResponseFactory {
        return new ResponseFactory();
    });
    $container->set(ServerRequestFactoryInterface::class, static function (): ServerRequestFactory {
        return new ServerRequestFactory();
    });
    $container->set(StreamFactoryInterface::class, static function (): StreamFactory {
        return new StreamFactory();
    });
    $container->set(JSendResponseFactory::class, static function (Container $container): JSendResponseFactory {
        return new JSendResponseFactory(
            $container->get(ResponseFactoryInterface::class),
            $container->get(StreamFactoryInterface::class)
        );
    });
    $container->set(Client::class, static function (): Client {
        return new Client(['http_errors' => false]);
    });
    ContainerCapabilityRegistrar::registerHttpClient($container, static function (Container $container): GuzzleClient {
        return new GuzzleClient($container->get(Client::class));
    });
};
