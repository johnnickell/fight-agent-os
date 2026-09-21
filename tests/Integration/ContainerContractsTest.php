<?php

declare(strict_types=1);

namespace Tests\Integration;

use Doctrine\DBAL\Connection;
use Fight\Common\Application\Auth\RequestService;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Auth\Security\TokenDecoder;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\FileStorage\FileStorage;
use Fight\Common\Application\FileStorage\StorageService;
use Fight\Common\Application\FileTransfer\Transport\FileTransport;
use Fight\Common\Application\FileTransfer\FileTransferService;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Observability\AuditLog;
use Fight\Common\Application\Observability\HealthAggregator;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Common\Application\Scheduler\Scheduler;
use Fight\Common\Application\Sms\SmsService;
use Fight\Common\Application\Socket\PrivatePublisher;
use Fight\Common\Application\Socket\Publisher;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Domain\Serialization\Serializer;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Factory\ResponseFactoryInterface;
use Psr\Http\Factory\ServerRequestFactoryInterface;
use Psr\Http\Factory\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ContainerContractsTest extends TestCase
{
    public function test_the_booted_slim_container_exposes_selected_public_contracts(): void
    {
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();

        foreach ([ValidatorInterface::class, PasswordHasher::class, PasswordValidator::class, TokenEncoder::class, TokenDecoder::class, RequestService::class, CacheItemPoolInterface::class, CacheInterface::class, Connection::class, TransactionalUnitOfWork::class, HttpClient::class, ClientInterface::class, ResponseFactoryInterface::class, ServerRequestFactoryInterface::class, StreamFactoryInterface::class, FileStorage::class, StorageService::class, FileTransport::class, FileTransferService::class, Filesystem::class, LoggerInterface::class, HealthAggregator::class, MetricsCollector::class, AuditLog::class, ProcessRunner::class, Scheduler::class, UrlGenerator::class, MailService::class, SmsService::class, Publisher::class, PrivatePublisher::class, TemplateEngine::class, Serializer::class] as $contract) {
            self::assertTrue($container->has($contract), $contract);
            self::assertIsObject($container->get($contract));
        }
    }
}
