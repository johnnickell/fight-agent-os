<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\QueryString;
use Fight\Common\Application\Attribute\Validation;

/**
 * Class QueryInput
 *
 * Declares a disposable query boundary for middleware behavior checks
 */
final class QueryInput
{
    /**
     * Handles validated input
     */
    #[QueryString]
    #[Validation(rules: [
        ['field' => 'page', 'label' => 'Page', 'rules' => 'required|digits|min_number[1]|max_number[100]'],
        ['field' => 'enabled', 'label' => 'Enabled', 'rules' => 'in_list[true,false]']
    ])]
    public function __invoke(): void
    {
    }
}
