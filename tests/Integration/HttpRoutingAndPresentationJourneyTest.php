<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Templating\TemplateEngine;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Tests\Fixture\Serialization\JourneySerializable;
use Fight\Common\Domain\Serialization\Serializer;

final class HttpRoutingAndPresentationJourneyTest extends TestCase
{
    public function test_booted_guzzle_transport_and_psr18_view_use_a_test_local_response_queue(): void
    {
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();
        $container->set(
            Client::class,
            static fn (): Client => new Client([
                'handler' => HandlerStack::create(new MockHandler([
                    new Response(202, ['X-Journey' => 'fight-http'], 'transport-ready'),
                    new Response(204, ['X-Journey' => 'psr18'], ''),
                ])),
                'http_errors' => false,
            ])
        );

        $request = new Request('GET', 'https://test.invalid/journey');
        $transportResponse = $container->get(HttpClient::class)->send($request);
        $psr18Response = $container->get(ClientInterface::class)->sendRequest($request);

        self::assertSame(202, $transportResponse->getStatusCode());
        self::assertSame('fight-http', $transportResponse->getHeaderLine('X-Journey'));
        self::assertSame('transport-ready', (string) $transportResponse->getBody());
        self::assertSame(204, $psr18Response->getStatusCode());
        self::assertSame('psr18', $psr18Response->getHeaderLine('X-Journey'));
    }

    public function test_booted_slim_url_generator_twig_and_json_serializer_have_real_output(): void
    {
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();

        self::assertSame('/?state=ready', $container->get(UrlGenerator::class)->generate('app.index', query: ['state' => 'ready']));
        self::assertSame('http://localhost/?state=ready', $container->get(UrlGenerator::class)->generate('app.index', query: ['state' => 'ready'], absolute: true));
        self::assertSame('Slim ready', $container->get(TemplateEngine::class)->render('profile', ['value' => 'ready']));

        $serialized = $container->get(Serializer::class)->serialize(new JourneySerializable('serialized-ready'));
        $restored = $container->get(Serializer::class)->deserialize($serialized);
        self::assertInstanceOf(JourneySerializable::class, $restored);
        self::assertSame('serialized-ready', $restored->value());
    }
}
