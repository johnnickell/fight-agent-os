<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Cache\PsrCache;
use Fight\Common\Application\Auth\RequestService;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Auth\Security\TokenDecoder;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\FileStorage\FileStorage;
use Fight\Common\Application\FileStorage\StorageService;
use Fight\Common\Application\FileTransfer\FileTransferService;
use Fight\Common\Application\FileTransfer\Transport\FileTransport;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Observability\AuditLog;
use Fight\Common\Application\Observability\HealthAggregator;
use Fight\Common\Application\Observability\HealthCheck;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Application\Process\ProcessBuilder;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Scheduler\Scheduler;
use Fight\Common\Application\Sms\SmsService;
use Fight\Common\Application\Socket\PrivatePublisher;
use Fight\Common\Application\Socket\Publisher;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Domain\Observability\AuditEntry;
use Fight\Common\Domain\Observability\HealthResult;
use Fight\Common\Domain\Observability\HealthStatus;
use Fight\Common\Domain\Serialization\Serializable;
use Fight\Common\Domain\Serialization\Serializer;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Factory\ResponseFactoryInterface;
use Psr\Http\Factory\ServerRequestFactoryInterface;
use Psr\Http\Factory\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

$projectRoot = dirname(__DIR__);

require $projectRoot.'/vendor/autoload.php';
require_once $projectRoot.'/scripts/framework-support-profile.php';

/** @param bool $condition */
$require = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

final class ProductionProfileSerializable implements Serializable
{
    public function __construct(private readonly string $value)
    {
    }

    public static function arrayDeserialize(array $data): static
    {
        return new self((string) ($data['value'] ?? ''));
    }

    public function arraySerialize(): array
    {
        return ['value' => $this->value];
    }

    public function value(): string
    {
        return $this->value;
    }
}

frameworkSupportAssertLane($projectRoot, frameworkSupportProfile($projectRoot));

putenv('APP_HMAC_KEY=production-profile-key');
putenv('APP_HMAC_SECRET=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
$require(getenv('APP_HMAC_KEY') === 'production-profile-key', 'The production verifier must explicitly configure APP_HMAC_KEY.');
$require(getenv('APP_HMAC_SECRET') === '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', 'The production verifier must explicitly configure APP_HMAC_SECRET.');

/** @var App $app */
$app = require $projectRoot.'/bootstrap/app.php';
$container = $app->getContainer();
$require($container instanceof ContainerInterface, 'Slim did not expose its configured container.');

$routes = array_values($app->getRouteCollector()->getRoutes());
$require(count($routes) === 1, 'Production Slim must expose exactly one route.');
$require($routes[0]->getPattern() === '/', 'Production Slim must expose only the root route.');
$require($routes[0]->getMethods() === ['GET'], 'Production Slim root route must be GET-only.');
$rootResponse = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/'));
$require($rootResponse->getStatusCode() === 200 && (string) $rootResponse->getBody() === 'Fight Slim starter is ready.', 'Production Slim root request failed.');

foreach ([
    CommandBus::class,
    QueryBus::class,
    EventDispatcher::class,
    ResponseFactoryInterface::class,
    ServerRequestFactoryInterface::class,
    StreamFactoryInterface::class,
    StorageService::class,
    FileTransport::class,
    FileTransferService::class,
    LoggerInterface::class,
    Publisher::class,
    PrivatePublisher::class,
] as $contract) {
    $require($container->has($contract), sprintf('Production Slim container is missing %s.', $contract));
    $require(is_object($container->get($contract)), sprintf('Production Slim failed to resolve %s.', $contract));
}

$request = (new ServerRequestFactory())
    ->createServerRequest('POST', 'https://profile.example.test/verify')
    ->withBody((new StreamFactory())->createStream('production-ready'));
$signed = $container->get(RequestService::class)->signRequest($request);
$require($signed->getHeaderLine('Credential') === 'production-profile-key' && $signed->getHeaderLine('Signature') !== '', 'Production HMAC request signing failed.');
$token = $container->get(TokenEncoder::class)->encode(['subject' => 'production-profile'], new \DateTimeImmutable('+5 minutes'));
$require($container->get(TokenDecoder::class)->decode($token)['subject'] === 'production-profile', 'Production JWT round trip failed.');
$password = $container->get(PasswordHasher::class)->hash('production-profile-password');
$require($container->get(PasswordValidator::class)->validate('production-profile-password', $password), 'Production password validation failed.');
$validator = $container->get(ValidatorInterface::class);
$require(count($validator->validate('profile@example.test', new Email())) === 0, 'Production validator rejected a valid email.');
$require(count($validator->validate('not-an-email', new Email())) === 1, 'Production validator accepted an invalid email.');

$cacheKey = 'production-profile-cache';
$pool = $container->get(CacheItemPoolInterface::class);
$item = $pool->getItem($cacheKey)->set('psr6-ready');
$pool->save($item);
$require($pool->getItem($cacheKey)->get() === 'psr6-ready', 'Production PSR-6 cache failed.');
$simpleCache = $container->get(CacheInterface::class);
$simpleCache->set($cacheKey, 'psr16-ready');
$require($simpleCache->get($cacheKey) === 'psr16-ready', 'Production PSR-16 cache failed.');
$loads = 0;
$require($container->get(PsrCache::class)->read('fight-'.$cacheKey, static function () use (&$loads): string {
    ++$loads;

    return 'fight-cache-ready';
}, 60) === 'fight-cache-ready', 'Production Fight cache failed.');
$require($loads === 1, 'Production Fight cache did not execute its loader exactly once.');
$pool->clear();

$connection = $container->get(Connection::class);
$connection->executeStatement('CREATE TABLE production_profile_transactions (value TEXT NOT NULL)');
try {
    $container->get(TransactionalUnitOfWork::class)->commitTransactional(static function () use ($connection): void {
        $connection->insert('production_profile_transactions', ['value' => 'committed']);
    });
    $require($connection->fetchFirstColumn('SELECT value FROM production_profile_transactions') === ['committed'], 'Production transactional unit of work failed.');
} finally {
    $connection->executeStatement('DROP TABLE IF EXISTS production_profile_transactions');
}

$container->set(Client::class, static fn (): Client => new Client([
    'handler' => HandlerStack::create(new MockHandler([
        new Response(202, ['X-Profile' => 'transport']),
        new Response(204, ['X-Profile' => 'psr18']),
    ])),
    'http_errors' => false,
]));
$httpRequest = new Request('GET', 'https://profile.invalid/runtime');
$require($container->get(HttpClient::class)->send($httpRequest)->getStatusCode() === 202, 'Production Fight HTTP transport failed.');
$require($container->get(ClientInterface::class)->sendRequest($httpRequest)->getStatusCode() === 204, 'Production PSR-18 transport failed.');

$prefix = 'production-profile-'.bin2hex(random_bytes(6));
$storagePath = $prefix.'/ready.txt';
$filesystemPath = $projectRoot.'/var/'.$prefix.'/ready.txt';
$lockPath = $projectRoot.'/var/scheduler/'.$prefix.'.lock';
$filesystem = $container->get(Filesystem::class);
try {
    $storage = $container->get(FileStorage::class);
    $storage->putFile($storagePath, 'storage-ready');
    $require($storage->getFileContents($storagePath) === 'storage-ready', 'Production Flysystem storage failed.');
    $filesystem->put($filesystemPath, 'filesystem-ready');
    $require($filesystem->get($filesystemPath) === 'filesystem-ready', 'Production filesystem failed.');

    $processOutput = '';
    $container->get(ProcessRunner::class)->attach(
        ProcessBuilder::create()->shellCommand('printf production-process-ready')->stdout(static function (string $output) use (&$processOutput): void {
            $processOutput .= $output;
        })->getProcess()
    );
    $container->get(ProcessRunner::class)->run();
    $require($processOutput === 'production-process-ready', 'Production process runner failed.');

    $scheduled = false;
    $scheduler = $container->get(Scheduler::class);
    $scheduler->addJob($prefix, static fn (): bool => true, static function () use (&$scheduled): bool {
        $scheduled = true;

        return true;
    });
    $scheduler->run();
    $require($scheduled, 'Production scheduler failed.');
} finally {
    $filesystem->remove([$projectRoot.'/var/storage/'.$prefix, $projectRoot.'/var/'.$prefix, $lockPath]);
}

$require($container->get(UrlGenerator::class)->generate('app.index', query: ['state' => 'ready']) === '/?state=ready', 'Production Slim URL generation failed.');
$require($container->get(TemplateEngine::class)->render('profile', ['value' => 'ready']) === 'Slim ready', 'Production Twig rendering failed.');
$serializer = $container->get(Serializer::class);
$serialized = $serializer->serialize(new ProductionProfileSerializable('serializer-ready'));
$restored = $serializer->deserialize($serialized);
$require($restored instanceof ProductionProfileSerializable && $restored->value() === 'serializer-ready', 'Production JSON serialization failed.');

$health = $container->get(HealthAggregator::class);
$health->addCheck(new class implements HealthCheck {
    public function name(): string
    {
        return 'production-profile';
    }

    public function check(): HealthResult
    {
        return new HealthResult('production-profile', HealthStatus::healthy(), 'ready');
    }
});
$require($health->report()->isHealthy(), 'Production health aggregation failed.');
$container->get(MetricsCollector::class)->increment('production.profile', ['state' => 'ready']);
$container->get(AuditLog::class)->record(AuditEntry::record('production-profile', 'verified'));
$container->get(MailService::class)->send($container->get(MailService::class)->createMessage());
$container->get(SmsService::class)->send($container->get(SmsService::class)->createMessage('+15555550100', '+15555550101', 'safe production profile sms'));

fwrite(STDOUT, "Production-installed Slim framework support profile passed.\n");
