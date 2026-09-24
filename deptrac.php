<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\Collector\PhpInteralConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $configuredPaths = getenv('DEPTRAC_PATHS');
    $paths = $configuredPaths === false || $configuredPaths === ''
        ? [__DIR__ . '/src']
        : array_map(
            static fn (string $path): string => str_starts_with($path, '/') ? $path : __DIR__ . '/' . $path,
            explode(PATH_SEPARATOR, $configuredPaths)
        );

    $config
        ->paths(...$paths)
        ->layers(
            $domain = Layer::withName('Agent OS Domain')->collectors(
                ClassLikeConfig::create('^App\\Domain\\')
            ),
            $application = Layer::withName('Agent OS Application')->collectors(
                ClassLikeConfig::create('^App\\Application\\')
            ),
            $adapter = Layer::withName('Agent OS Adapter')->collectors(
                ClassLikeConfig::create('^App\\Adapter\\')
            ),
            $accessControlDomain = Layer::withName('Access Control Domain')->collectors(
                ClassLikeConfig::create('^Fight\\AccessControl\\Domain\\')
            ),
            $accessControlApplication = Layer::withName('Access Control Application')->collectors(
                ClassLikeConfig::create('^Fight\\AccessControl\\Application\\')
            ),
            $commonDomain = Layer::withName('Fight Common Domain')->collectors(
                ClassLikeConfig::create('^Fight\\Common\\Domain\\')
            ),
            $commonApplication = Layer::withName('Fight Common Application')->collectors(
                ClassLikeConfig::create('^Fight\\Common\\Application\\')
            ),
            $commonAdapter = Layer::withName('Fight Common Adapter')->collectors(
                ClassLikeConfig::create('^Fight\\Common\\Adapter\\')
            ),
            $infrastructure = Layer::withName('Infrastructure')->collectors(
                ClassLikeConfig::create('^(?:Doctrine|GuzzleHttp|Lcobucci|League|Monolog|Psr|Slim|Symfony|Twig)\\')
            ),
            $phpInternals = Layer::withName('PHP internals')->collectors(
                PhpInteralConfig::create('.*')
            )
        )
        ->rulesets(
            Ruleset::forLayer($domain)->accesses(
                $domain,
                $accessControlDomain,
                $commonDomain,
                $phpInternals
            ),
            Ruleset::forLayer($application)->accesses(
                $application,
                $domain,
                $accessControlDomain,
                $accessControlApplication,
                $commonDomain,
                $commonApplication,
                $phpInternals
            ),
            Ruleset::forLayer($adapter)->accesses(
                $adapter,
                $application,
                $domain,
                $accessControlDomain,
                $accessControlApplication,
                $commonDomain,
                $commonApplication,
                $commonAdapter,
                $infrastructure,
                $phpInternals
            ),
            Ruleset::forLayer($accessControlDomain),
            Ruleset::forLayer($accessControlApplication),
            Ruleset::forLayer($commonDomain),
            Ruleset::forLayer($commonApplication),
            Ruleset::forLayer($commonAdapter),
            Ruleset::forLayer($infrastructure),
            Ruleset::forLayer($phpInternals)
        )
        ->cacheFile(__DIR__ . '/var/cache/deptrac.cache');
};
