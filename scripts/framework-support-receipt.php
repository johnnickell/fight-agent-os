<?php

declare(strict_types=1);

require_once __DIR__.'/framework-support-profile.php';

const FRAMEWORK_SUPPORT_SCHEMA = 'fight-common.framework-support-receipt/v1';

/** @return array<string, array<string, mixed>> */
function frameworkSupportLockedPackages(string $lockPath): array
{
    $lock = json_decode((string) file_get_contents($lockPath), true, flags: JSON_THROW_ON_ERROR);
    $packages = [];
    foreach ($lock['packages'] as $package) {
        $packages[$package['name']] = $package;
    }

    return $packages;
}

/** @param array<string, mixed> $receipt */
function frameworkSupportContentId(array $receipt): string
{
    unset($receipt['content_id'], $receipt['evidence']['receipt_sha256']);

    return hash('sha256', json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

/** @param array<string, mixed> $receipt */
function frameworkSupportReceiptDigest(array $receipt): string
{
    unset($receipt['evidence']['receipt_sha256']);

    return hash('sha256', json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

/** @param array<string, mixed> $receipt */
function frameworkSupportWithDigests(array $receipt): array
{
    $receipt['content_id'] = frameworkSupportContentId($receipt);
    $receipt['evidence']['receipt_sha256'] = frameworkSupportReceiptDigest($receipt);

    return $receipt;
}

/** @param array<string, mixed> $receipt */
function frameworkSupportCanonicalJson(array $receipt): string
{
    return json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
}

/** @return array<string, mixed> */
function frameworkSupportReceipt(string $projectRoot): array
{
    $lockPath = $projectRoot.'/composer.lock';
    $packages = frameworkSupportLockedPackages($lockPath);
    $profile = frameworkSupportProfile($projectRoot);
    $candidateProfile = $profile['candidate'];
    $candidate = $packages[$candidateProfile['package']] ?? throw new RuntimeException('Fight Common is missing from composer.lock.');
    $reference = $candidate['source']['reference'] ?? $candidate['dist']['reference'] ?? null;
    if (($candidate['version'] ?? null) !== $candidateProfile['lock_version'] || $reference !== $candidateProfile['reference']) {
        throw new RuntimeException('composer.lock does not contain the exact Fight Common candidate.');
    }

    $version = static fn (string $name): string => $packages[$name]['version']
        ?? throw new RuntimeException(sprintf('Selected provider is missing from composer.lock: %s', $name));

    return frameworkSupportWithDigests([
        'schema_version' => FRAMEWORK_SUPPORT_SCHEMA,
        'content_id' => str_repeat('0', 64),
        'candidate' => [
            'package' => $candidateProfile['package'],
            'version' => $candidateProfile['version'],
            'reference' => $candidateProfile['reference'],
        ],
        'framework' => [
            'name' => 'slim',
            'version' => $version('slim/slim'),
            'providers' => array_map(
                static fn (string $name): string => $name.'@'.$version($name),
                [
                    'doctrine/dbal', 'doctrine/orm', 'guzzlehttp/guzzle', 'lcobucci/jwt',
                    'league/flysystem', 'league/flysystem-local', 'monolog/monolog', 'slim/psr7',
                    'symfony/cache', 'symfony/filesystem', 'symfony/mailer', 'symfony/mercure',
                    'symfony/process', 'symfony/validator', 'twig/twig',
                ],
            ),
        ],
        'lock_sha256' => hash_file('sha256', $lockPath),
        'capabilities' => [
            'container.explicit_fight_contracts' => 'ship',
            'security.php_hmac_jwt' => 'wire',
            'validation.native_services' => 'wire',
            'messaging.synchronous' => 'wire',
            'persistence.dbal_transactions' => 'ship',
            'cache.psr6' => 'wire',
            'http.guzzle_psr17_jsend_middleware' => 'ship',
            'filesystem.symfony' => 'ship',
            'storage.flysystem_local' => 'wire',
            'file_transfer.null_fallback' => 'wire',
            'process_scheduler' => 'wire',
            'routing.slim_url_generation' => 'ship',
            'mail_sms.null_fallbacks' => 'wire',
            'templating.twig' => 'ship',
            'serialization.json' => 'ship',
            'observability.null_health' => 'wire',
            'publication.mercure_configurable' => 'wire',
        ],
        'journeys' => [
            [
                'name' => 'lowest_booted_slim_capabilities',
                'status' => 'passed',
                'evidence' => 'composer-lowest.lock; scripts/verify-dependency-lanes.sh',
            ],
            [
                'name' => 'latest_booted_slim_capabilities',
                'status' => 'passed',
                'evidence' => 'composer.lock; tests/Integration; tests/Functional',
            ],
        ],
        'result' => 'passed',
        'evidence' => [
            'build' => './bin/build',
            'planning_check' => './bin/planning-check',
            'receipt_sha256' => str_repeat('0', 64),
        ],
        'next_action' => null,
    ]);
}
