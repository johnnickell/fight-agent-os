<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

/**
 * Class RegistrationSources
 *
 * Holds the explicit Repository-owned equivalence set with bounded unique claims
 */
final readonly class RegistrationSources
{
    /** @var list<SourceDescriptor> */
    public array $equivalent;

    /**
     * Constructs RegistrationSources
     *
     * @phpstan-param list<SourceDescriptor> $equivalent
     */
    public function __construct(public ?SourceDescriptor $primary = null, array $equivalent = [])
    {
        if (count($equivalent) > 7 || ($primary === null && $equivalent !== [])) {
            throw new RegistrationInputRejected();
        }
        $keys = $primary === null ? [] : [$primary->key() => true];
        foreach ($equivalent as $source) {
            if (isset($keys[$source->key()])) {
                throw new RegistrationInputRejected();
            }
            $keys[$source->key()] = true;
        }
        usort($equivalent, static fn(SourceDescriptor $a, SourceDescriptor $b): int => strcmp($a->key(), $b->key()));
        $this->equivalent = $equivalent;
    }

    /**
     * Returns every approved claim in canonical order
     *
     * @return list<SourceDescriptor>
     */
    public function all(): array
    {
        return $this->primary === null ? [] : [$this->primary, ...$this->equivalent];
    }
}
