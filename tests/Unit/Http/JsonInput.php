<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\JsonBody;
use Fight\Common\Application\Attribute\Validation;

/**
 * Class JsonInput
 *
 * Declares a disposable mutation boundary for middleware behavior checks
 */
final class JsonInput
{
    /**
     * Handles validated input
     */
    #[JsonBody]
    #[Validation(rules: [
        ['field' => 'page', 'label' => 'Page', 'rules' => 'required|type[int]|min_number[1]'],
        ['field' => 'enabled', 'label' => 'Enabled', 'rules' => 'type[bool]']
    ])]
    public function handle(): void
    {
    }
}
