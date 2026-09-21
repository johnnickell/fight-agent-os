<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fight\Common\Application\Auth\RequestService;
use Fight\Common\Application\Auth\Security\TokenDecoder;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Security\Environment;

final class SecurityConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        Environment::apply();
    }

    public function test_jwt_encoder_requires_an_explicit_hmac_secret_when_resolved(): void
    {
        Environment::clear('APP_HMAC_SECRET');
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_HMAC_SECRET must be configured.');

        $container->get(TokenEncoder::class);
    }

    public function test_jwt_decoder_requires_an_explicit_hmac_secret_when_resolved(): void
    {
        Environment::clear('APP_HMAC_SECRET');
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_HMAC_SECRET must be configured.');

        $container->get(TokenDecoder::class);
    }

    public function test_hmac_request_service_requires_an_explicit_key_when_resolved(): void
    {
        Environment::clear('APP_HMAC_KEY');
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_HMAC_KEY must be configured.');

        $container->get(RequestService::class);
    }

    public function test_hmac_request_service_requires_an_explicit_secret_when_resolved(): void
    {
        Environment::clear('APP_HMAC_SECRET');
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_HMAC_SECRET must be configured.');

        $container->get(RequestService::class);
    }
}
