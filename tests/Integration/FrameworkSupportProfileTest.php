<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Fixture\FrameworkSupport\DependencyLaneFixture;

require_once __DIR__.'/../../scripts/framework-support-profile.php';

final class FrameworkSupportProfileTest extends TestCase
{
    private DependencyLaneFixture $lane;

    protected function setUp(): void
    {
        $this->lane = DependencyLaneFixture::create();
    }

    protected function tearDown(): void
    {
        $this->lane->remove();
    }

    public function test_a_clean_lane_matches_the_complete_runtime_profile_in_both_composer_manifests(): void
    {
        frameworkSupportAssertLane($this->lane->root(), frameworkSupportProfile($this->lane->root()));

        self::addToAssertionCount(1);
    }

    public function test_an_unselected_package_in_the_lock_is_rejected(): void
    {
        $this->lane->injectLockedPackage('dragonmantank/cron-expression');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unselected package is present in composer.lock: dragonmantank/cron-expression');
        frameworkSupportAssertLane($this->lane->root(), frameworkSupportProfile($this->lane->root()));
    }

    public function test_an_unselected_package_in_the_installed_manifest_is_rejected(): void
    {
        $this->lane->injectInstalledPackage('dragonmantank/cron-expression');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unselected package is present in vendor/composer/installed.json: dragonmantank/cron-expression');
        frameworkSupportAssertLane($this->lane->root(), frameworkSupportProfile($this->lane->root()));
    }

    public function test_a_selected_package_missing_from_the_installed_manifest_is_rejected(): void
    {
        $this->lane->removeInstalledPackage('symfony/cache');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Selected runtime package is missing from vendor/composer/installed.json: symfony/cache');
        frameworkSupportAssertLane($this->lane->root(), frameworkSupportProfile($this->lane->root()));
    }
}
