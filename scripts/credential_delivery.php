<?php

declare(strict_types=1);

use App\Application\CredentialDelivery\ExpireCredentialDeliveryPages;
use App\Application\CredentialDelivery\RecoverCredentialDeliveryPage;
use Fight\AccessControl\Application\AccessControl\ActivationGrant\Service\InvitationDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetDeliveryCipher;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindCredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDeliveryId;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;

require dirname(__DIR__).'/vendor/autoload.php';

set_error_handler(static function (): never {
    throw new RuntimeException('Credential recovery runtime warning.');
});
$stage = 'arguments';
try {
    $arguments = array_slice($_SERVER['argv'], 1);
    $operation = array_shift($arguments);
    if (!in_array($operation, ['run', 'expire', 'status'], true) || !in_array('--internal', $arguments, true)) {
        throw new InvalidArgumentException('Explicit internal operation is required.');
    }
    $options = [];
    $positional = [];
    foreach ($arguments as $argument) {
        if ($argument === '--internal') {
            if (isset($options['internal'])) {
                throw new InvalidArgumentException('Repeated authority option.');
            }
            $options['internal'] = true;
        } elseif (
            preg_match(
                '/^--(page-size|capacity|lease-seconds|expiry-page-size|expiry-pages)=([0-9]+)$/D',
                $argument,
                $matches
            ) === 1
        ) {
            if (
                $operation === 'status' || isset($options[$matches[1]])
                || ($operation === 'expire' && !str_starts_with($matches[1], 'expiry-'))
            ) {
                throw new InvalidArgumentException('Invalid operational option.');
            }
            $number = filter_var($matches[2], FILTER_VALIDATE_INT);
            if ($number === false || $number < 1 || $number > 1000) {
                throw new InvalidArgumentException('Invalid operational bound.');
            }
            $options[$matches[1]] = $number;
        } elseif (str_starts_with($argument, '--')) {
            throw new InvalidArgumentException('Unsupported operational option.');
        } else {
            $positional[] = $argument;
        }
    }
    if (($options['lease-seconds'] ?? 300) !== 300) {
        throw new InvalidArgumentException('The qualified package does not allow lease overrides.');
    }
    if (($options['expiry-page-size'] ?? 50) > 100 || ($options['expiry-pages'] ?? 10) > 10) {
        throw new InvalidArgumentException('Invalid expiry bound.');
    }
    if (($operation !== 'status' && $positional !== []) || ($operation === 'status' && count($positional) !== 2)) {
        throw new InvalidArgumentException('Invalid operation arguments.');
    }
    $statusQuery = $operation === 'status' ? new FindCredentialDeliveryStatus(...$positional) : null;
    // Validate purpose and package ID before opening operational capabilities.
    if ($statusQuery !== null) {
        match ($positional[0]) {
            'activation' => ActivationDeliveryId::fromString($positional[1]),
            'password_reset' => PasswordResetDeliveryId::fromString($positional[1]),
            'email_change' => EmailChangeDeliveryId::fromString($positional[1])
        };
    }
    $stage = 'runtime';
    $container = require dirname(__DIR__).'/config/services.php';
    $queries = $container->get(QueryBus::class);
    if ($statusQuery !== null) {
        $status = $queries->fetch($statusQuery);
        $result = ['found' => $status !== null, 'delivery' => $status?->toArray()];
        $exit = $status === null ? 3 : 0;
    } else {
        if ($operation === 'run') {
            // Fail closed before mutating state; cleanup-only operation needs none of these capabilities.
            $container->get(CredentialDeliveryProvider::class);
            $container->get(InvitationDeliveryCipher::class);
            $container->get(PasswordResetDeliveryCipher::class);
        }
        $cleanup = new ExpireCredentialDeliveryPages(
            $queries,
            $container->get(SynchronousCommandBus::class),
            $container->get(Clock::class)
        );
        $result = ['expiry' => $cleanup->run($options['expiry-page-size'] ?? 50, $options['expiry-pages'] ?? 10)];
        $exit = $result['expiry']['stalled'] ? 1 : 0;
        if ($operation === 'run' && !$result['expiry']['stalled']) {
            $runner = new RecoverCredentialDeliveryPage(
                $queries,
                $container->get(CommandBus::class),
                $container->get(Clock::class)
            );
            $result['delivery'] = $runner->run($options['page-size'] ?? 100, $options['capacity'] ?? 100);
            $exit = $result['delivery']['unsupported'] > 0 ? 1 : 0;
        }
    }
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n");
    exit($exit);
} catch (Throwable) {
    $message = "Credential delivery operation failed; no sensitive diagnostic material is emitted.\n";
    if ($stage === 'arguments') {
        $message = "Invalid arguments. Usage: ./bin/credential-delivery run --internal\n";
        $message .= "       [--page-size=N] [--capacity=N] [--lease-seconds=300]\n";
        $message .= "       [--expiry-page-size=1..100] [--expiry-pages=1..10]\n";
        $message .= "       ./bin/credential-delivery expire --internal [--expiry-page-size=N] [--expiry-pages=N]\n";
        $message .= "       ./bin/credential-delivery status PURPOSE DELIVERY_ID --internal\n";
    }
    fwrite(STDERR, $message);
    exit($stage === 'arguments' ? 2 : 1);
}
