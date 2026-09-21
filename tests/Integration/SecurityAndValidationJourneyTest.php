<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use Fight\Common\Application\Auth\RequestService;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Auth\Security\TokenDecoder;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SecurityAndValidationJourneyTest extends TestCase
{
    public function test_booted_security_services_sign_requests_and_round_trip_jwts(): void
    {
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://service.example/profile?source=journey')
            ->withBody((new StreamFactory())->createStream('{"state":"ready"}'));

        $signed = $container->get(RequestService::class)->signRequest($request);
        self::assertSame('HMAC-SHA256', $signed->getHeaderLine('Authorization'));
        self::assertSame('test-hmac-key', $signed->getHeaderLine('Credential'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signed->getHeaderLine('Signature'));
        self::assertNotSame('', $signed->getHeaderLine('X-Nonce'));
        self::assertSame(hash('sha256', '{"state":"ready"}'), $signed->getHeaderLine('X-Content-SHA256'));

        $token = $container->get(TokenEncoder::class)->encode(['subject' => 'security-journey'], new DateTimeImmutable('+5 minutes'));
        self::assertSame('security-journey', $container->get(TokenDecoder::class)->decode($token)['subject']);

        $hash = $container->get(PasswordHasher::class)->hash('correct-horse-battery-staple');
        self::assertTrue($container->get(PasswordValidator::class)->validate('correct-horse-battery-staple', $hash));
        self::assertFalse($container->get(PasswordValidator::class)->validate('wrong-password', $hash));
    }

    public function test_booted_native_validator_reports_valid_and_invalid_values(): void
    {
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();
        $validator = $container->get(ValidatorInterface::class);

        self::assertCount(0, $validator->validate('journey@example.test', new Email()));
        self::assertCount(1, $validator->validate('not an email', new Email()));
    }
}
