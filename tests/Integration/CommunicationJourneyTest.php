<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
use Fight\Common\Adapter\Mail\Null\NullMailTransport;
use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Common\Application\Sms\SmsService;
use Fight\Common\Application\Socket\PrivatePublisher;
use Fight\Common\Application\Socket\Publisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\MockHub;
use Tests\Fixture\Security\Environment;
use Tests\Fixture\Socket\RecordingHub;

final class CommunicationJourneyTest extends TestCase
{
    protected function tearDown(): void
    {
        Environment::clear('MAILER_DSN');
        Environment::clear('MERCURE_URL');
        Environment::clear('MERCURE_JWT');
    }

    public function test_default_mail_and_sms_fallbacks_accept_messages_without_external_delivery(): void
    {
        Environment::clear('MAILER_DSN');
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();

        self::assertInstanceOf(NullMailTransport::class, $container->get(MailTransport::class));
        self::assertInstanceOf(MockHub::class, $container->get(HubInterface::class));
        $container->get(MailService::class)->send($container->get(MailService::class)->createMessage());
        $container->get(SmsService::class)->send($container->get(SmsService::class)->createMessage('+15555550100', '+15555550101', 'safe local sms'));
        self::addToAssertionCount(1);
    }

    public function test_configured_mailer_branch_uses_a_null_dsn_without_contacting_a_provider(): void
    {
        putenv('MAILER_DSN=null://null');
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();

        self::assertInstanceOf(SymfonyMailTransport::class, $container->get(MailTransport::class));
        $message = $container->get(MailService::class)->createMessage()
            ->addFrom('from@example.test')
            ->addTo('to@example.test')
            ->setSubject('Capability journey')
            ->addContent('safe local mailer', 'text/plain');
        $container->get(MailService::class)->send($message);
        self::addToAssertionCount(1);
    }

    public function test_configured_mercure_branch_constructs_without_publishing_and_recording_hub_observes_routes(): void
    {
        putenv('MERCURE_URL=https://mercure.example.test/.well-known/mercure');
        putenv('MERCURE_JWT=test-mercure-token');
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();

        $configuredHub = $container->get(HubInterface::class);
        self::assertInstanceOf(Hub::class, $configuredHub);
        self::assertSame('https://mercure.example.test/.well-known/mercure', $configuredHub->getUrl());

        $recordingHub = new RecordingHub();
        $container->set(HubInterface::class, static fn (): RecordingHub => $recordingHub);
        $container->get(Publisher::class)->push('public-topic', 'public-message');
        $container->get(PrivatePublisher::class)->pushPrivate('private-topic', 'private-message');

        self::assertCount(2, $recordingHub->updates);
        self::assertFalse($recordingHub->updates[0]->isPrivate());
        self::assertTrue($recordingHub->updates[1]->isPrivate());

    }

    public function test_configured_mercure_hub_requires_an_explicit_jwt(): void
    {
        putenv('MERCURE_URL=https://mercure.example.test/.well-known/mercure');
        Environment::clear('MERCURE_JWT');
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MERCURE_JWT must be configured when MERCURE_URL is set.');

        $container->get(HubInterface::class);
    }
}
