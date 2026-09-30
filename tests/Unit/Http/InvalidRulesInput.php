<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\QueryString;
use Fight\Common\Application\Attribute\Validation;

/**
 * Class InvalidRulesInput
 *
 * Declares an unsupported rule for rejection checks
 */
final class InvalidRulesInput
{
    /**
     * Handles input
     */
    #[QueryString]
    #[Validation(rules: [['field' => 'page', 'label' => 'Page', 'rules' => 'unknown_rule']])]
    public function __invoke(): void
    {
    }
}
