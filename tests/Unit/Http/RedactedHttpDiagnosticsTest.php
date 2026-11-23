<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Api\Failure\RedactedHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Proves diagnostics do not send internal messages, causes or anonymous class paths to the logger
 */
final class RedactedHttpDiagnosticsTest extends TestCase
{
    /**
     * Does not log expected permission refusals as faults
     */
    public function test_that_expected_refusal_is_not_logged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        (new RedactedHttpDiagnostics($logger, new SafeHttpErrorPolicy()))->report(new PermissionDenied());
    }

    /**
     * Records only a status and a named exception type for an unexpected fault
     */
    public function test_that_fault_details_do_not_reach_the_logger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('HTTP request failed.', [
            'http_status'    => 500,
            'exception_type' => RuntimeException::class
        ]);

        (new RedactedHttpDiagnostics($logger, new SafeHttpErrorPolicy()))->report(
            new RuntimeException('private request data', previous: new RuntimeException('private cause'))
        );
    }

    /**
     * Substitutes a neutral label for anonymous class names that can include source paths
     */
    public function test_that_anonymous_exception_name_does_not_reach_the_logger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('HTTP request failed.', [
            'http_status'    => 500,
            'exception_type' => 'Throwable'
        ]);
        $fault = new class ('private request data') extends RuntimeException {
        };

        (new RedactedHttpDiagnostics($logger, new SafeHttpErrorPolicy()))->report($fault);
    }
}
