<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\Failure;

use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class RedactedHttpDiagnostics
 *
 * Redacts each handled failure before any logger processor, handler or formatter
 */
final readonly class RedactedHttpDiagnostics implements SafeHttpDiagnostics
{
    /**
     * Constructs RedactedHttpDiagnostics
     */
    public function __construct(private LoggerInterface $logger, private SafeHttpErrorPolicy $httpErrorPolicy)
    {
    }

    /**
     * @inheritDoc
     */
    public function report(Throwable $failure): void
    {
        // Expected refusals are deliberately filtered here, not before Common offers the failure.
        $status = $this->httpErrorPolicy->status($failure);
        if ($status < 500) {
            return;
        }

        // Anonymous class names can contain source paths. No arbitrary class name reaches the logger.
        $type = $failure::class;
        if (
            strlen($type) > 196
            || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/D', $type) !== 1
        ) {
            $type = 'Throwable';
        }
        $this->logger->error('HTTP request failed.', ['http_status' => $status, 'exception_type' => $type]);
    }
}
