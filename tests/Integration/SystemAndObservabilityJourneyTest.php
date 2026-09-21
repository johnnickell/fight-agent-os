<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\Observability\AuditLog;
use Fight\Common\Application\Observability\HealthAggregator;
use Fight\Common\Application\Observability\HealthCheck;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Application\Process\ProcessBuilder;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Common\Application\Scheduler\Scheduler;
use Fight\Common\Domain\Observability\AuditEntry;
use Fight\Common\Domain\Observability\HealthResult;
use Fight\Common\Domain\Observability\HealthStatus;
use PHPUnit\Framework\TestCase;

final class SystemAndObservabilityJourneyTest extends TestCase
{
    public function test_booted_process_runner_and_scheduler_execute_local_work_and_remove_lock_artifacts(): void
    {
        $root = dirname(__DIR__, 2);
        $container = (require $root.'/bootstrap/app.php')->getContainer();
        $output = '';
        $container->get(ProcessRunner::class)->attach(
            ProcessBuilder::create()
                ->shellCommand('printf process-ready')
                ->stdout(static function (string $data) use (&$output): void {
                    $output .= $data;
                })
                ->getProcess()
        );
        $container->get(ProcessRunner::class)->run();
        self::assertSame('process-ready', $output);

        $jobName = 'capability-journey-'.bin2hex(random_bytes(6));
        $lockPath = $root.'/var/scheduler/'.$jobName.'.lock';
        $ran = false;

        try {
            $scheduler = $container->get(Scheduler::class);
            $scheduler->addJob($jobName, static fn (): bool => true, static function () use (&$ran): bool {
                $ran = true;

                return true;
            });
            $scheduler->run();
            self::assertTrue($ran);
        } finally {
            $container->get(Filesystem::class)->remove($lockPath);
        }

        self::assertFalse($container->get(Filesystem::class)->exists($lockPath));
    }

    public function test_booted_observability_contracts_aggregate_health_and_accept_null_sink_events(): void
    {
        $container = (require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2)))->getContainer();
        $health = $container->get(HealthAggregator::class);
        $health->addCheck(new class implements HealthCheck {
            public function name(): string
            {
                return 'journey';
            }

            public function check(): HealthResult
            {
                return new HealthResult('journey', HealthStatus::healthy(), 'ready');
            }
        });

        $report = $health->report();
        self::assertTrue($report->isHealthy());
        self::assertSame('journey', $report->results()[0]->name());
        self::assertSame('ready', $report->results()[0]->message());

        $container->get(MetricsCollector::class)->increment('capability.journey', ['state' => 'ready']);
        $container->get(AuditLog::class)->record(AuditEntry::record('capability-journey', 'verified', ['safe' => true]));
        self::addToAssertionCount(1);
    }
}
