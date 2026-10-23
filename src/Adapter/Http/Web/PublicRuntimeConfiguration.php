<?php

declare(strict_types=1);

namespace App\Adapter\Http\Web;

use RuntimeException;
use Throwable;

/**
 * Class PublicRuntimeConfiguration
 *
 * Validates independent diagnostics and exact documentation eligibility
 */
final readonly class PublicRuntimeConfiguration
{
    /**
     * Constructs PublicRuntimeConfiguration
     */
    public function __construct(public bool $appDebug = false, public bool $swaggerAvailable = false)
    {
    }

    /**
     * Parses operator settings without disclosing their values
     */
    public static function parse(string|false $environment, string|false $debug, string|false $allowed): self
    {
        if ($debug !== false && !in_array($debug, ['true', 'false'], true)) {
            throw new RuntimeException('Invalid application diagnostic configuration.');
        }
        if ($debug === 'true' && in_array($environment, ['prod', 'production'], true)) {
            throw new RuntimeException('Application diagnostics are prohibited in production.');
        }
        $named = is_string($environment) && preg_match('/\A[a-z][a-z0-9_-]{0,31}\z/D', $environment) === 1;
        $eligible = false;
        try {
            $names = $allowed === false ? null : json_decode($allowed, false, 8, JSON_THROW_ON_ERROR);
            if (!is_array($names) || !array_is_list($names) || count($names) > 32) {
                throw new RuntimeException();
            }
            foreach ($names as $name) {
                if (!is_string($name) || preg_match('/\A[a-z][a-z0-9_-]{0,31}\z/D', $name) !== 1) {
                    throw new RuntimeException();
                }
            }
            if (count(array_unique($names)) !== count($names)) {
                throw new RuntimeException();
            }
            $eligible = $named && in_array($environment, $names, true);
        } catch (Throwable) {
            // The bounded unavailable response is the diagnostic; no invalid list can enable exposure.
        }

        return new self($debug === 'true' && $named, $eligible);
    }

    /**
     * Returns exactly the approved version-two presentation fields
     *
     * @return array{schema_version: int, api_base_path: string, app_debug: bool, swagger_available: bool}
     */
    public function toArray(): array
    {
        return [
            'schema_version'    => 2,
            'api_base_path'     => '/api/v1',
            'app_debug'         => $this->appDebug,
            'swagger_available' => $this->swaggerAvailable
        ];
    }
}
