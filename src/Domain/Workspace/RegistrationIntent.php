<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

/**
 * Class RegistrationIntent
 *
 * Binds retries to canonical metadata and the explicitly approved source set
 */
final readonly class RegistrationIntent
{
    /**
     * Constructs RegistrationIntent
     */
    public function __construct(
        public RegistrationMetadata $metadata,
        public RegistrationSources $sources,
        public string $idempotencyKey
    ) {
        if (!preg_match('/\A[A-Za-z0-9_-]{8,128}\z/D', $idempotencyKey)) {
            throw new RegistrationInputRejected();
        }
    }

    /**
     * Returns the immutable canonical request digest without retaining raw input
     */
    public function digest(): string
    {
        return hash('sha256', json_encode([
            'version'            => 1,
            'display_name'       => $this->metadata->displayName,
            'slug'               => $this->metadata->slug,
            'primary_source'     => $this->sources->primary?->toArray(),
            'equivalent_sources' => array_map(
                static fn(SourceDescriptor $s): array => $s->toArray(),
                $this->sources->equivalent
            )
        ], JSON_THROW_ON_ERROR));
    }
}
