<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\QueryString;
use Fight\Common\Application\Attribute\Validation;

/**
 * Class EmptyFieldRulesInput
 *
 * Supplies a query field without validation
 */
final class EmptyFieldRulesInput
{
    /**
     * Handles input
     */
    #[QueryString]
    #[Validation(rules: [['field' => 'page', 'label' => 'Page', 'rules' => '']])]
    public function handle(): void
    {
    }
}
